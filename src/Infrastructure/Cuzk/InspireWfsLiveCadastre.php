<?php

declare(strict_types=1);

namespace App\Infrastructure\Cuzk;

use App\Domain\Parcel\LiveCadastre;
use App\Domain\Parcel\LiveCadastreUnavailable;
use App\Domain\Parcel\LiveParcelState;
use App\Infrastructure\Http\CurlHttpClient;
use DOMDocument;
use DOMXPath;
use RuntimeException;

/** Aktuální stav parcely z INSPIRE WFS ČÚZK (Cadastral Parcels). */
final readonly class InspireWfsLiveCadastre implements LiveCadastre
{
    private const URL = 'https://services.cuzk.cz/wfs/inspire-cp-wfs.asp';

    public function __construct(private CurlHttpClient $http)
    {
    }

    public function fetch(int $parcelId): ?LiveParcelState
    {
        $query = http_build_query([
            'service' => 'WFS',
            'version' => '2.0.0',
            'request' => 'GetFeature',
            'StoredQuery_id' => 'urn:ogc:def:query:OGC-WFS::GetFeatureById',
            'ID' => 'CP.' . $parcelId,
        ]);

        try {
            $xml = $this->http->get(self::URL . '?' . $query, 8);
        } catch (RuntimeException $e) {
            throw new LiveCadastreUnavailable('WFS ČÚZK neodpovídá', previous: $e);
        }

        return $this->parse($xml);
    }

    public function parse(string $xml): ?LiveParcelState
    {
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml, LIBXML_NONET)) {
            throw new LiveCadastreUnavailable('WFS ČÚZK vrátil neplatné XML');
        }
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cp', 'http://inspire.ec.europa.eu/schemas/cp/4.0');

        $parcel = $xpath->query('//cp:CadastralParcel')->item(0);
        if ($parcel === null) {
            if ($xpath->query('//*[local-name()="ExceptionReport"]')->length > 0) {
                throw new LiveCadastreUnavailable('WFS ČÚZK vrátil chybu');
            }

            return null;
        }

        $since = $xpath->evaluate('string(cp:beginLifespanVersion)', $parcel);

        return new LiveParcelState(
            label: trim($xpath->evaluate('string(cp:label)', $parcel)),
            area: (int) $xpath->evaluate('string(cp:areaValue)', $parcel),
            validSince: $since === '' ? null : substr($since, 0, 10),
        );
    }
}
