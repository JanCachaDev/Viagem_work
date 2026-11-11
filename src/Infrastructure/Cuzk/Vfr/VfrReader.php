<?php

declare(strict_types=1);

namespace App\Infrastructure\Cuzk\Vfr;

use App\Application\Import\Record\CadastralAreaRecord;
use App\Application\Import\Record\MunicipalityRecord;
use App\Application\Import\Record\ParcelRecord;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Generator;
use RuntimeException;
use XMLReader;

/**
 * Proudově čte výměnný formát RÚIAN (soubory mají desítky MB) – do paměti se
 * rozbalí vždy jen jeden prvek Obec / KatastralniUzemi / Parcela.
 */
final readonly class VfrReader
{
    public const MUNICIPALITY = 'Obec';
    public const CADASTRAL_AREA = 'KatastralniUzemi';
    public const PARCEL = 'Parcela';

    private const NS = [
        'vf' => 'urn:cz:isvs:ruian:schemas:VymennyFormatTypy:v1',
        'obi' => 'urn:cz:isvs:ruian:schemas:ObecIntTypy:v1',
        'oki' => 'urn:cz:isvs:ruian:schemas:OkresIntTypy:v1',
        'kui' => 'urn:cz:isvs:ruian:schemas:KatUzIntTypy:v1',
        'pai' => 'urn:cz:isvs:ruian:schemas:ParcelaIntTypy:v1',
        'com' => 'urn:cz:isvs:ruian:schemas:CommonTypy:v1',
        'gml' => 'http://www.opengis.net/gml/3.2',
    ];

    public function __construct(private GmlToWkt $gml = new GmlToWkt())
    {
    }

    /**
     * @param list<string> $types
     * @return Generator<MunicipalityRecord|CadastralAreaRecord|ParcelRecord>
     */
    public function read(string $uri, array $types = [self::MUNICIPALITY, self::CADASTRAL_AREA, self::PARCEL]): Generator
    {
        $reader = new XMLReader();
        if (!$reader->open($uri, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE)) {
            throw new RuntimeException("Nelze otevřít {$uri}");
        }

        try {
            $hasNode = $reader->read();
            while ($hasNode) {
                // Kontejner KÚ se jmenuje stejně jako samotné KÚ, záznamy poznáme podle gml:id.
                if ($reader->nodeType !== XMLReader::ELEMENT
                    || $reader->namespaceURI !== self::NS['vf']
                    || !in_array($reader->localName, $types, true)
                    || $reader->getAttributeNs('id', self::NS['gml']) === null) {
                    $hasNode = $reader->read();
                    continue;
                }
                $element = new VfrElement(...$this->expand($reader));
                yield match ($reader->localName) {
                    self::MUNICIPALITY => $this->municipality($element),
                    self::CADASTRAL_AREA => $this->cadastralArea($element),
                    self::PARCEL => $this->parcel($element),
                };
                $hasNode = $reader->next();
            }
        } finally {
            $reader->close();
        }
    }

    /** @return array{DOMXPath, DOMElement} */
    private function expand(XMLReader $reader): array
    {
        $doc = new DOMDocument();
        $node = $reader->expand($doc);
        if (!$node instanceof DOMElement) {
            throw new RuntimeException('Chybné XML ve VFR');
        }
        $xpath = new DOMXPath($doc);
        foreach (self::NS as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        return [$xpath, $node];
    }

    private function municipality(VfrElement $e): MunicipalityRecord
    {
        $district = $e->value('obi:Okres/oki:Kod');

        return new MunicipalityRecord(
            (int) $e->value('obi:Kod'),
            (string) $e->value('obi:Nazev'),
            $district === null ? null : (int) $district,
        );
    }

    private function cadastralArea(VfrElement $e): CadastralAreaRecord
    {
        return new CadastralAreaRecord(
            (int) $e->value('kui:Kod'),
            (string) $e->value('kui:Nazev'),
            (int) $e->value('kui:Obec/obi:Kod'),
            $this->gml->convert($e->element('kui:Geometrie/kui:OriginalniHranice/*')),
        );
    }

    private function parcel(VfrElement $e): ParcelRecord
    {
        $sub = $e->value('pai:PododdeleniCisla');
        $landUse = $e->value('pai:ZpusobyVyuzitiPozemku');
        $validFrom = $e->value('pai:PlatiOd');

        $bpej = [];
        foreach ($e->elements('pai:BonitovaneDily/com:BonitovanyDil') as $part) {
            $bpej[] = [
                'code' => str_pad((string) $e->value('com:BonitovanaJednotkaKod', $part), 5, '0', STR_PAD_LEFT),
                'area' => (int) $e->value('com:Vymera', $part),
            ];
        }

        return new ParcelRecord(
            id: (int) $e->value('pai:Id'),
            cadastralAreaCode: (int) $e->value('pai:KatastralniUzemi/kui:Kod'),
            stem: (int) $e->value('pai:KmenoveCislo'),
            sub: $sub === null ? null : (int) $sub,
            buildingPlot: $e->value('pai:DruhCislovaniKod') === '1',
            area: (int) $e->value('pai:VymeraParcely'),
            landType: (int) $e->value('pai:DruhPozemkuKod'),
            landUse: $landUse === null ? null : (int) $landUse,
            protections: array_values(array_unique(array_map(
                fn (DOMElement $n) => (int) $n->textContent,
                $e->elements('pai:ZpusobyOchranyPozemku/com:ZpusobOchrany/com:Kod'),
            ))),
            bpej: $bpej,
            validFrom: $validFrom === null ? null : substr($validFrom, 0, 10),
            wkt: $this->gml->convert($e->element('pai:Geometrie/pai:OriginalniHranice/*')),
        );
    }
}
