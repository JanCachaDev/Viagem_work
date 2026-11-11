<?php

declare(strict_types=1);

namespace App\Application\Import\Port;

use App\Application\Import\Record\CodeLists;

interface CodeListSource
{
    public function load(): CodeLists;
}
