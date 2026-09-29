<?php

namespace App\Core;

class Migrator
{
    public function run(): array
    {
        $pdo = Database::getConnection();

        $lock = $pdo->query("SELECT GET_LOCK('famo_schema_migrations', 30)")->fetchColumn();
        if ((int) $lock !== 1) {
            throw new \RuntimeException('Could not acquire migration lock', 500);
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
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

                if ($sql === false) {
                    throw new \RuntimeException("Unable to read migration {$name}", 500);
                }
                $statements = self::splitStatements($sql);
                if ($statements === []) {
                    continue;
                }

                try {
                    foreach ($statements as $statement) {
                        $pdo->exec($statement);
                    }
                    $insert = $pdo->prepare("INSERT INTO migrations (migration, executed_at) VALUES (?, NOW())");
                    $insert->execute([$name]);
                    $ran[] = $name;
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw new \RuntimeException("Migration {$name} failed", 500, $e);
                }
            }

            return $ran;
        } finally {
            $pdo->query("SELECT RELEASE_LOCK('famo_schema_migrations')");
        }
    }

    /**
     * Split ordinary SQL scripts at semicolons without splitting quoted text
     * or treating semicolons in line/block comments as statement boundaries.
     * Stored procedures / custom DELIMITER scripts are intentionally unsupported.
     */
    public static function splitStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $lineComment = false;
        $blockComment = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($char === "\n") {
                    $lineComment = false;
                    $buffer .= "\n";
                }
                continue;
            }
            if ($blockComment) {
                if ($char === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                    $buffer .= ' ';
                }
                continue;
            }

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $quote !== '`' && $next !== '') {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                if ($char === $quote) {
                    if ($next === $quote) {
                        $buffer .= $next;
                        $i++;
                    } else {
                        $quote = null;
                    }
                }
                continue;
            }

            if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2])))
                || $char === '#') {
                $lineComment = true;
                $i += $char === '#' ? 0 : 1;
                continue;
            }
            if ($char === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }
            if ($char === ';') {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if ($quote !== null || $blockComment) {
            throw new \InvalidArgumentException('Unterminated SQL quote or block comment');
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }
}
