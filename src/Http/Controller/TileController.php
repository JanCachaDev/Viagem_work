<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Application\Tile\GetTile;
use App\Domain\Tile\TileCoordinate;
use App\Http\HttpException;
use App\Http\Response;
use InvalidArgumentException;

final readonly class TileController
{
    public function __construct(private GetTile $getTile)
    {
    }

    /** @param array<string, int> $params */
    public function show(array $params): Response
    {
        try {
            $tile = new TileCoordinate($params['z'], $params['x'], $params['y']);
        } catch (InvalidArgumentException $e) {
            throw new HttpException(400, $e->getMessage());
        }

        return Response::vectorTile(($this->getTile)($tile));
    }
}
