<?php

declare(strict_types=1);

use App\Config;
use App\Http\Request;
use App\Kernel;

require __DIR__ . '/../vendor/autoload.php';

$kernel = new Kernel(Config::fromEnvironment(dirname(__DIR__)));
$kernel->handle(Request::fromGlobals())->send();
