<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Shared\BoundingBox;

final class BboxSql
{
    /** SQL výraz: bbox geometrie ve WGS84 jako text "west,south,east,north". */
    public static function select(string $geometry): string
    {
        return "(SELECT concat_ws(',', ST_XMin(b), ST_YMin(b), ST_XMax(b), ST_YMax(b))
                 FROM ST_Transform(ST_Envelope({$geometry}), 4326) AS b)";
    }

    public static function parse(string $value): BoundingBox
    {
        [$west, $south, $east, $north] = array_map(fn (string $v) => round((float) $v, 6), explode(',', $value));

        return new BoundingBox($west, $south, $east, $north);
    }
}
