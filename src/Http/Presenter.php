<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\Parcel\LiveComparison;
use App\Domain\Parcel\BpejPart;
use App\Domain\Parcel\Parcel;
use App\Domain\Shared\SearchHit;

/** Převod doménových objektů na JSON strukturu API. */
final class Presenter
{
    /** @return array<string, mixed> */
    public static function parcel(Parcel $parcel): array
    {
        return [
            'id' => $parcel->id,
            'number' => (string) $parcel->number,
            'buildingPlot' => $parcel->number->buildingPlot,
            'cadastralArea' => ['code' => $parcel->cadastralArea->code, 'name' => $parcel->cadastralArea->name],
            'municipality' => ['code' => $parcel->municipality->code, 'name' => $parcel->municipality->name],
            'area' => $parcel->area,
            'landType' => ['code' => $parcel->landType->code, 'name' => $parcel->landType->name],
            'landUse' => $parcel->landUse === null ? null : ['code' => $parcel->landUse->code, 'name' => $parcel->landUse->name],
            'protections' => $parcel->protections,
            'bpej' => array_map(fn (BpejPart $part) => [
                'code' => $part->code,
                'area' => $part->area,
                'pricePerM2' => $part->pricePerM2,
            ], $parcel->bpej),
            'estimatedOfficialPrice' => $parcel->estimatedOfficialPrice(),
            'validFrom' => $parcel->validFrom?->format('Y-m-d'),
            'bbox' => $parcel->bbox->toArray(),
            'geometry' => json_decode($parcel->geoJson, true),
            'links' => [
                'nahlizeniDoKn' => 'https://nahlizenidokn.cuzk.cz/ZobrazObjekt.aspx?typ=parcela&id=' . $parcel->id,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function liveComparison(LiveComparison $comparison): array
    {
        return [
            'existsLive' => $comparison->existsLive,
            'live' => $comparison->live === null ? null : [
                'label' => $comparison->live->label,
                'area' => $comparison->live->area,
                'validSince' => $comparison->live->validSince,
            ],
            'differences' => $comparison->differences,
            'upToDate' => $comparison->differences === [],
        ];
    }

    /** @return array<string, mixed> */
    public static function searchHit(SearchHit $hit): array
    {
        return [
            'type' => $hit->type,
            'id' => $hit->id,
            'label' => $hit->label,
            'detail' => $hit->detail,
            'bbox' => $hit->bbox->toArray(),
        ];
    }
}
