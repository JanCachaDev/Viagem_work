<?php

declare(strict_types=1);

namespace App\Application\Parcel;

use App\Domain\Parcel\LiveCadastre;

final readonly class CompareWithLiveCadastre
{
    public function __construct(
        private GetParcelDetail $getParcel,
        private LiveCadastre $liveCadastre,
    ) {
    }

    public function __invoke(int $id): LiveComparison
    {
        $parcel = ($this->getParcel)($id);

        return LiveComparison::of($parcel, $this->liveCadastre->fetch($id));
    }
}
