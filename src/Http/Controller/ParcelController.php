<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Parcel\CompareWithLiveCadastre;
use App\Application\Parcel\GetParcelDetail;
use App\Application\Parcel\SearchParcels;
use App\Domain\Parcel\LiveCadastreUnavailable;
use App\Http\HttpException;
use App\Http\Presenter;
use App\Http\RateLimiter;
use App\Http\Request;
use App\Http\Response;

final readonly class ParcelController
{
    public function __construct(
        private GetParcelDetail $getParcel,
        private SearchParcels $search,
        private CompareWithLiveCadastre $compareLive,
        private RateLimiter $liveRateLimiter,
    ) {
    }

    /** @param array<string, int> $params */
    public function show(array $params): Response
    {
        return Response::json(Presenter::parcel(($this->getParcel)($params['id'])), maxAge: 300);
    }

    /** @param array<string, int> $params */
    public function live(array $params, Request $request): Response
    {
        if (!$this->liveRateLimiter->allow('live:' . $request->clientIp)) {
            throw new HttpException(429, 'Příliš mnoho dotazů na katastr, zkus to za chvíli');
        }

        try {
            return Response::json(Presenter::liveComparison(($this->compareLive)($params['id'])), maxAge: 300);
        } catch (LiveCadastreUnavailable $e) {
            throw new HttpException(502, $e->getMessage());
        }
    }

    /** @param array<string, int> $params */
    public function search(array $params, Request $request): Response
    {
        $hits = ($this->search)($request->queryString('q'));

        return Response::json(['results' => array_map(Presenter::searchHit(...), $hits)], maxAge: 300);
    }
}
