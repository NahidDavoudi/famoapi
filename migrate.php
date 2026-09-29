<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $migrator = new \App\Core\Migrator();
    $result = $migrator->run();
    echo json_encode(['executed' => $result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (\Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . PHP_EOL);
    error_log((string) $exception);
    exit(1);
}
