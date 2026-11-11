<?php

declare(strict_types=1);

use App\Config;
use App\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['obce:', 'help']);
if (isset($options['help'])) {
    echo <<<TXT
    Import parcel okresu z výměnného formátu RÚIAN.

      php bin/import.php                      celý okres (výchozí Jičín, DISTRICT_CODE=3604)
      php bin/import.php --obce=572659,573051 jen vybrané obce (kódy obcí RÚIAN)

    TXT;
    exit(0);
}

$only = isset($options['obce'])
    ? array_map('intval', array_filter(explode(',', (string) $options['obce'])))
    : [];

set_time_limit(0);
$kernel = new Kernel(Config::fromEnvironment(dirname(__DIR__)));
$config = $kernel->config();
$started = microtime(true);

try {
    ($kernel->importDistrict())(
        $config->districtCode,
        $config->districtName,
        array_values($only),
        static fn (string $message) => fwrite(STDOUT, '[' . date('H:i:s') . "] {$message}\n"),
    );
} catch (Throwable $e) {
    fwrite(STDERR, "Import selhal: {$e->getMessage()}\n");
    exit(1);
}

printf("Import trval %.0f s\n", microtime(true) - $started);
