<?php

declare(strict_types=1);

namespace App;

final readonly class Config
{
    public function __construct(
        public string $dbDsn,
        public string $dbUser,
        public string $dbPassword,
        public string $dbImportUser,
        public string $dbImportPassword,
        public string $cacheDir,
        public string $dataDir,
        public int $districtCode,
        public string $districtName,
        public ?string $vfrDate,
        public int $liveRequestsPerMinute,
    ) {
    }

    public static function fromEnvironment(string $projectRoot): self
    {
        $env = static fn (string $name, string $default = ''): string => (string) (getenv($name) ?: $default);

        return new self(
            dbDsn: $env('DB_DSN', 'pgsql:host=127.0.0.1;port=5433;dbname=parcely'),
            dbUser: $env('DB_USER', 'parcely_web'),
            dbPassword: $env('DB_PASSWORD'),
            dbImportUser: $env('DB_IMPORT_USER', 'parcely'),
            dbImportPassword: $env('DB_IMPORT_PASSWORD'),
            cacheDir: $env('CACHE_DIR', $projectRoot . '/var/cache'),
            dataDir: $env('DATA_DIR', $projectRoot . '/var/data'),
            districtCode: (int) $env('DISTRICT_CODE', '3604'),
            districtName: $env('DISTRICT_NAME', 'Jičín'),
            vfrDate: $env('VFR_DATE') ?: null,
            liveRequestsPerMinute: (int) $env('LIVE_REQUESTS_PER_MINUTE', '30'),
        );
    }
}
