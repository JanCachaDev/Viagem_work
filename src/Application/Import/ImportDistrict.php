<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Import\Port\CadastreSource;
use App\Application\Import\Port\CodeListSource;
use App\Application\Import\Port\ImportStore;
use App\Application\Import\Record\MunicipalityRecord;
use App\Domain\Tile\TileCache;
use Closure;
use InvalidArgumentException;

final readonly class ImportDistrict
{
    public function __construct(
        private CadastreSource $cadastre,
        private CodeListSource $codeLists,
        private ImportStore $store,
        private TileCache $tileCache,
    ) {
    }

    /**
     * @param list<int> $onlyMunicipalities prázdné = celý okres
     * @param Closure(string): void $log
     */
    public function __invoke(int $districtCode, string $districtName, array $onlyMunicipalities, Closure $log): int
    {
        $log('Číselníky ISKN…');
        $this->store->saveCodeLists($this->codeLists->load());

        $municipalities = $this->selectMunicipalities($districtCode, $onlyMunicipalities);
        $log(sprintf('Dataset %s, obcí k importu: %d', $this->cadastre->datasetDate(), count($municipalities)));

        $total = 0;
        foreach ($municipalities as $i => $municipality) {
            $started = microtime(true);
            $count = $this->store->replaceMunicipality(
                $municipality,
                $this->cadastre->municipalityContent($municipality->code),
            );
            $total += $count;
            $log(sprintf(
                '[%d/%d] %s (%d): %d parcel, %.1f s',
                $i + 1,
                count($municipalities),
                $municipality->name,
                $municipality->code,
                $count,
                microtime(true) - $started,
            ));
        }

        $this->store->finish($districtName, $this->cadastre->datasetDate());
        $this->tileCache->clear();
        $log("Hotovo, celkem {$total} parcel. Cache dlaždic smazána.");

        return $total;
    }

    /**
     * @param list<int> $only
     * @return list<MunicipalityRecord>
     */
    private function selectMunicipalities(int $districtCode, array $only): array
    {
        $all = $this->cadastre->municipalitiesInDistrict($districtCode);
        if ($only === []) {
            return $all;
        }

        $selected = array_values(array_filter($all, fn (MunicipalityRecord $m) => in_array($m->code, $only, true)));
        $missing = array_diff($only, array_map(fn (MunicipalityRecord $m) => $m->code, $selected));
        if ($missing !== []) {
            throw new InvalidArgumentException('Obce nejsou v okrese: ' . implode(', ', $missing));
        }

        return $selected;
    }
}
