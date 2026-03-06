<?php
require 'bootstrap/autoload.php';
App\Core\Support\Env::load(__DIR__);
App\Core\Config\Config::load('config');
$db = App\Core\Database\DatabaseManager::getInstance()->getConnection('default');
var_dump($db->query('SELECT * FROM variantes LIMIT 1')->fetchAll(PDO::FETCH_ASSOC));
