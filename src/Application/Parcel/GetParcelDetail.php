<?php

declare(strict_types=1);

namespace App\Application\Parcel;

use App\Application\NotFound;
use App\Domain\Parcel\Parcel;
use App\Domain\Parcel\ParcelRepository;

final readonly class GetParcelDetail
{
    public function __construct(private ParcelRepository $parcels)
    {
    }

    public function __invoke(int $id): Parcel
    {
        return $this->parcels->find($id) ?? throw new NotFound("Parcela {$id} neexistuje");
    }
}
