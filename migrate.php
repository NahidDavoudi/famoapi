<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$migrator = new \App\Core\Migrator();
$result = $migrator->run();

echo json_encode(['executed' => $result], JSON_UNESCAPED_UNICODE) . PHP_EOL;