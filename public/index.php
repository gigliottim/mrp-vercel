<?php

declare(strict_types=1);

use App\Core\Http\Request;

$kernel = require __DIR__ . '/../bootstrap/app.php';

$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
