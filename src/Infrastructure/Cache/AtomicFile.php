<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use RuntimeException;

final class AtomicFile
{
    /** Zápis přes dočasný soubor + rename, aby nginx/čtenář nikdy neviděl rozepsaný soubor. */
    public static function write(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Nelze vytvořit adresář {$dir}");
        }
        $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($tmp, $content) === false || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new RuntimeException("Nelze zapsat {$path}");
        }
    }
}
