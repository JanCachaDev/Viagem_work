<?php

declare(strict_types=1);

namespace App\Infrastructure\Cuzk;

use App\Application\Import\Port\CodeListSource;
use App\Application\Import\Record\CodeLists;
use App\Infrastructure\Http\CurlHttpClient;
use RuntimeException;
use ZipArchive;

/** Oficiální číselníky ISKN (CSV v ZIPu, kódování windows-1250). */
final readonly class IsknCodeListSource implements CodeListSource
{
    private const BASE_URL = 'https://services.cuzk.cz/sestavy/cis/';

    public function __construct(
        private CurlHttpClient $http,
        private string $dataDir,
    ) {
    }

    public function load(): CodeLists
    {
        $landTypes = [];
        foreach ($this->rows('SC_D_POZEMKU') as $row) {
            $landTypes[(int) $row['KOD']] = ['name' => $row['NAZEV'], 'agricultural' => $row['ZEMEDELSKA_KULTURA'] === 'a'];
        }

        $landUses = [];
        foreach ($this->rows('SC_ZP_VYUZITI_POZ') as $row) {
            $landUses[(int) $row['KOD']] = $row['NAZEV'];
        }

        $protections = [];
        foreach ($this->rows('SC_ZP_OCHRANY_NEM') as $row) {
            $protections[(int) $row['KOD']] = $row['NAZEV'];
        }

        $bpejPrices = [];
        foreach ($this->rows('SC_BPEJ') as $row) {
            if ($row['CENA'] !== '' && ctype_digit($row['KOD'])) {
                $bpejPrices[str_pad($row['KOD'], 5, '0', STR_PAD_LEFT)] = (float) $row['CENA'];
            }
        }

        return new CodeLists($landTypes, $landUses, $protections, $bpejPrices);
    }

    /** @return list<array<string, string>> platné řádky (bez data konce platnosti) */
    private function rows(string $name): array
    {
        $lines = preg_split('/\r?\n/', trim($this->download($name)));
        $header = str_getcsv((string) array_shift($lines), ';', '"', '');

        $rows = [];
        foreach ($lines as $line) {
            $values = array_map('trim', str_getcsv($line, ';', '"', ''));
            if (count($values) !== count($header)) {
                continue;
            }
            $row = array_combine($header, $values);
            if (($row['PLATNOST_DO'] ?? '') === '') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function download(string $name): string
    {
        $zipPath = "{$this->dataDir}/{$name}.zip";
        if (!is_file($zipPath)) {
            if (!is_dir($this->dataDir)) {
                mkdir($this->dataDir, 0775, true);
            }
            $this->http->download(self::BASE_URL . $name . '.zip', $zipPath);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Poškozený archiv {$zipPath}");
        }
        $content = $zip->getFromIndex(0);
        $zip->close();
        if ($content === false) {
            throw new RuntimeException("Prázdný archiv {$zipPath}");
        }

        return mb_check_encoding($content, 'UTF-8') ? $content : (string) iconv('WINDOWS-1250', 'UTF-8', $content);
    }
}
