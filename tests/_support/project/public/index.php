<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';

use FSi\Tests\App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel('test', true);
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
