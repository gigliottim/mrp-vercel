<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/autoload.php';

use App\Core\Config\Config;
use App\Core\Support\Env;
use App\Core\Support\SessionManager;

Env::load(base_path('.env'));
Config::load(base_path('config'));

SessionManager::start();
$_SESSION['diag'] = ['ts' => time(), 'ok' => true];

$sid = session_id();
$savePath = session_save_path();
$filePath = rtrim($savePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sess_' . $sid;

session_write_close();

$exists = file_exists($filePath);
$size = $exists ? (int) filesize($filePath) : -1;
$content = $exists ? (string) file_get_contents($filePath) : '';

echo 'SID=' . $sid . PHP_EOL;
echo 'SAVE_PATH=' . $savePath . PHP_EOL;
echo 'FILE=' . $filePath . PHP_EOL;
echo 'EXISTS=' . ($exists ? '1' : '0') . PHP_EOL;
echo 'SIZE=' . $size . PHP_EOL;
echo 'CONTENT=' . $content . PHP_EOL;
