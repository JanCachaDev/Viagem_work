<?php

declare(strict_types=1);

use App\Config;
use App\Domain\Tile\ZoomLevels;
use App\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['min:', 'max:']);
$minZoom = (int) ($options['min'] ?? 8);
$maxZoom = min((int) ($options['max'] ?? ZoomLevels::MAX), ZoomLevels::MAX);

set_time_limit(0);
$kernel = new Kernel(Config::fromEnvironment(dirname(__DIR__)));
$bbox = $kernel->catalog()->summary()->bbox;
if ($bbox === null) {
    fwrite(STDERR, "Databáze je prázdná, nejdřív spusť bin/import.php\n");
    exit(1);
}

$started = microtime(true);
$count = ($kernel->warmTileCache())(
    $bbox,
    $minZoom,
    $maxZoom,
    static function (int $zoom, int $done, int $total): void {
        fwrite(STDOUT, sprintf("\rzoom %2d: %5d / %5d", $zoom, $done, $total) . ($done === $total ? "\n" : ''));
    },
);

printf("Vygenerováno %d dlaždic za %.0f s\n", $count, microtime(true) - $started);
