<?php

require __DIR__ . '/bootstrap/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

use App\Repositories\SearchRepository;
use App\Core\Database\DatabaseManager;

$repo = new SearchRepository(DatabaseManager::connection());
$results = $repo->searchPartes(isset($argv[1]) ? $argv[1] : 'test', [], 10);

echo "\n--- RAW RESULTS FROM REPO using 'test' ---\n";
print_r($results);

// Now test Service formatting
$service = new \App\Services\SearchService($repo);
$formatted = $service->searchPartes(isset($argv[1]) ? $argv[1] : 'test', ['limit' => 5]);

echo "\n--- FORMATTED RESULTS ---\n";
print_r($formatted['results']);
