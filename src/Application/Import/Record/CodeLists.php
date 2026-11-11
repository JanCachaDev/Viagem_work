<?php

declare(strict_types=1);

namespace App\Application\Import\Record;

final readonly class CodeLists
{
    /**
     * @param array<int, array{name: string, agricultural: bool}> $landTypes
     * @param array<int, string> $landUses
     * @param array<int, string> $protections
     * @param array<string, float> $bpejPrices
     */
    public function __construct(
        public array $landTypes,
        public array $landUses,
        public array $protections,
        public array $bpejPrices,
    ) {
    }
}
