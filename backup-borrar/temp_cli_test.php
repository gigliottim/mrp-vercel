<?php
$kernel = require __DIR__ . '/bootstrap/app.php';
$request = new App\Core\Http\Request('GET', '/');
$response = $kernel->handle($request);
$response->send();
