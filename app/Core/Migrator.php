<?php

namespace App\Core;

class Migrator
{
    public function run(): array
    {
        $pdo = Database::getConnection();

        $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");

        $stmt = $pdo->query("SELECT migration FROM migrations");
        $executed = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        $files = glob(__DIR__ . '/../../database/migrations/*.sql');
        sort($files);

        $ran = [];

        foreach ($files as $file) {
            $name = basename($file);

            if (in_array($name, $executed, true)) {
                continue;
            }

            $sql = file_get_contents($file);

            if (empty(trim($sql))) {
                continue;
            }

            try {
                $pdo->exec($sql);
                $insert = $pdo->prepare("INSERT INTO migrations (migration, executed_at) VALUES (?, NOW())");
                $insert->execute([$name]);
                $ran[] = $name;
            } catch (\Throwable $e) {
                error_log("Migration {$name} failed: " . $e->getMessage());
            }
        }

        return $ran;
    }
}