<?php

declare(strict_types=1);

namespace App\Infrastructure\Cuzk\Vfr;

use App\Application\Import\Port\CadastreSource;
use App\Application\Import\Record\MunicipalityRecord;
use App\Infrastructure\Http\CurlHttpClient;
use DateTimeImmutable;
use RuntimeException;
use ZipArchive;

/**
 * Stahuje výměnný formát RÚIAN z vdp.cuzk.cz (stav k poslednímu dni měsíce).
 * Stažené soubory zůstávají v data adresáři, opakovaný import je nestahuje znovu.
 */
final class VfrCadastreSource implements CadastreSource
{
    private const BASE_URL = 'https://vdp.cuzk.cz/vymenny_format/soucasna/';

    private ?string $resolvedDate = null;

    public function __construct(
        private readonly CurlHttpClient $http,
        private readonly VfrReader $reader,
        private readonly string $dataDir,
        private readonly ?string $configuredDate = null,
    ) {
    }

    public function datasetDate(): string
    {
        return $this->resolvedDate ??= $this->configuredDate ?? $this->findLatestDate();
    }

    public function municipalitiesInDistrict(int $districtCode): array
    {
        $municipalities = [];
        foreach ($this->reader->read($this->localXml('ST_UZSZ'), [VfrReader::MUNICIPALITY]) as $municipality) {
            if ($municipality instanceof MunicipalityRecord && $municipality->districtCode === $districtCode) {
                $municipalities[] = $municipality;
            }
        }
        usort($municipalities, fn ($a, $b) => strcoll($a->name, $b->name));

        return $municipalities;
    }

    public function municipalityContent(int $municipalityCode): iterable
    {
        return $this->reader->read(
            $this->localXml("OB_{$municipalityCode}_UKSH"),
            [VfrReader::CADASTRAL_AREA, VfrReader::PARCEL],
        );
    }

    private function findLatestDate(): string
    {
        $month = new DateTimeImmutable('first day of this month');
        for ($i = 1; $i <= 3; $i++) {
            $date = $month->modify("-{$i} month")->format('Ymt');
            if ($this->http->exists($this->url($date, 'ST_UZSZ'))) {
                return $date;
            }
        }

        throw new RuntimeException('Nepodařilo se najít aktuální datovou sadu VFR, nastav VFR_DATE=YYYYMMDD');
    }

    private function localXml(string $name): string
    {
        $date = $this->datasetDate();
        if (!is_dir($this->dataDir) && !mkdir($this->dataDir, 0775, true) && !is_dir($this->dataDir)) {
            throw new RuntimeException("Nelze vytvořit {$this->dataDir}");
        }
        $zipPath = "{$this->dataDir}/{$date}_{$name}.xml.zip";
        if (!is_file($zipPath)) {
            $this->http->download($this->url($date, $name), $zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true || $zip->numFiles < 1) {
            @unlink($zipPath);
            throw new RuntimeException("Poškozený archiv {$zipPath}");
        }
        $entry = (string) $zip->getNameIndex(0);
        $zip->close();

        return 'zip://' . $zipPath . '#' . $entry;
    }

    private function url(string $date, string $name): string
    {
        return self::BASE_URL . "{$date}_{$name}.xml.zip";
    }
}
