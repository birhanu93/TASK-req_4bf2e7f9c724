<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\App\HttpApplication;
use App\App\Kernel;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

$kernel = Kernel::fromEnv();
$app = new HttpApplication($kernel);

$request = SymfonyRequest::createFromGlobals();
$response = $app->handle($request);
$response->send();
