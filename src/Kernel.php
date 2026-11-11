<?php

declare(strict_types=1);

namespace App;

use App\Application\Import\ImportDistrict;
use App\Application\NotFound;
use App\Application\Parcel\CompareWithLiveCadastre;
use App\Application\Parcel\GetParcelDetail;
use App\Application\Parcel\SearchParcels;
use App\Application\Tile\GetTile;
use App\Application\Tile\WarmTileCache;
use App\Domain\Cadastre\CadastreCatalog;
use App\Domain\Parcel\ParcelRepository;
use App\Domain\Tile\TileCache;
use App\Http\Controller\MetaController;
use App\Http\Controller\ParcelController;
use App\Http\Controller\TileController;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Infrastructure\Cache\FileCache;
use App\Infrastructure\Cache\FileRateLimiter;
use App\Infrastructure\Cache\FileTileCache;
use App\Infrastructure\Cuzk\CachedLiveCadastre;
use App\Infrastructure\Cuzk\InspireWfsLiveCadastre;
use App\Infrastructure\Cuzk\IsknCodeListSource;
use App\Infrastructure\Cuzk\Vfr\VfrCadastreSource;
use App\Infrastructure\Cuzk\Vfr\VfrReader;
use App\Infrastructure\Http\CurlHttpClient;
use App\Infrastructure\Persistence\PdoFactory;
use App\Infrastructure\Persistence\PostGisCadastreCatalog;
use App\Infrastructure\Persistence\PostGisImportStore;
use App\Infrastructure\Persistence\PostGisParcelRepository;
use App\Infrastructure\Persistence\PostGisTileProvider;
use Closure;
use PDO;
use Throwable;

/** Composition root: jediné místo, kde se skládají závislosti (líně, každá služba jednou). */
final class Kernel
{
    /** @var array<string, object> */
    private array $services = [];

    public function __construct(private readonly Config $config)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router()->dispatch($request);
        } catch (HttpException $e) {
            $response = Response::error($e->status, $e->getMessage());
        } catch (NotFound $e) {
            $response = Response::error(404, $e->getMessage());
        } catch (Throwable $e) {
            error_log((string) $e);
            $response = Response::error(500, 'Interní chyba serveru');
        }

        return $response->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public function router(): Router
    {
        return $this->service(Router::class, function (): Router {
            $tiles = new TileController(new GetTile(new PostGisTileProvider($this->webDb()), $this->tileCache()));
            $parcels = $this->parcelController();
            $meta = new MetaController($this->catalog());

            return (new Router())
                ->get('/tiles/{z}/{x}/{y}.pbf', fn (array $p) => $tiles->show($p))
                ->get('/api/meta', fn () => $meta->show())
                ->get('/api/search', fn (array $p, Request $r) => $parcels->search($p, $r))
                ->get('/api/parcels/{id}', fn (array $p) => $parcels->show($p))
                ->get('/api/parcels/{id}/live', fn (array $p, Request $r) => $parcels->live($p, $r));
        });
    }

    public function importDistrict(): ImportDistrict
    {
        $http = new CurlHttpClient();

        return new ImportDistrict(
            new VfrCadastreSource($http, new VfrReader(), $this->config->dataDir, $this->config->vfrDate),
            new IsknCodeListSource($http, $this->config->dataDir . '/ciselniky'),
            new PostGisImportStore($this->importDb()),
            $this->tileCache(),
        );
    }

    public function warmTileCache(): WarmTileCache
    {
        return new WarmTileCache(new GetTile(new PostGisTileProvider($this->webDb()), $this->tileCache()));
    }

    public function catalog(): CadastreCatalog
    {
        return $this->service(CadastreCatalog::class, fn () => new PostGisCadastreCatalog($this->webDb()));
    }

    public function config(): Config
    {
        return $this->config;
    }

    private function parcelController(): ParcelController
    {
        $getParcel = new GetParcelDetail($this->parcels());
        $live = new CachedLiveCadastre(
            new InspireWfsLiveCadastre(new CurlHttpClient()),
            new FileCache($this->config->cacheDir . '/live'),
        );

        return new ParcelController(
            $getParcel,
            new SearchParcels($this->parcels(), $this->catalog()),
            new CompareWithLiveCadastre($getParcel, $live),
            new FileRateLimiter($this->config->cacheDir . '/rate-limit', $this->config->liveRequestsPerMinute, 60),
        );
    }

    private function parcels(): ParcelRepository
    {
        return $this->service(ParcelRepository::class, fn () => new PostGisParcelRepository($this->webDb()));
    }

    private function tileCache(): TileCache
    {
        return $this->service(TileCache::class, fn () => new FileTileCache($this->config->cacheDir . '/tiles'));
    }

    private function webDb(): PDO
    {
        return $this->service('db.web', fn () => PdoFactory::create(
            $this->config->dbDsn,
            $this->config->dbUser,
            $this->config->dbPassword,
        ));
    }

    private function importDb(): PDO
    {
        return $this->service('db.import', fn () => PdoFactory::create(
            $this->config->dbDsn,
            $this->config->dbImportUser,
            $this->config->dbImportPassword,
        ));
    }

    /**
     * @template T of object
     * @param Closure(): T $factory
     * @return T
     */
    private function service(string $id, Closure $factory): object
    {
        return $this->services[$id] ??= $factory();
    }
}
