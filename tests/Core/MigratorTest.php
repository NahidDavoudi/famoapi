<?php

namespace Tests\Core;

use App\Core\Migrator;
use PHPUnit\Framework\TestCase;

class MigratorTest extends TestCase
{
    public function testSplitStatementsPreservesQuotedSemicolonsAndDropsComments(): void
    {
        $statements = Migrator::splitStatements(<<<'SQL'
            -- first statement
            INSERT INTO notes (body) VALUES ('one;two'); /* inline ; comment */
            INSERT INTO notes (body) VALUES ("three;four");
            # final statement
            SELECT `column;name` FROM notes;
            SQL);

        self::assertSame([
            "INSERT INTO notes (body) VALUES ('one;two')",
            'INSERT INTO notes (body) VALUES ("three;four")',
            'SELECT `column;name` FROM notes',
        ], $statements);
    }

    public function testSplitStatementsHandlesEscapedQuotes(): void
    {
        $statements = Migrator::splitStatements("INSERT INTO notes VALUES ('it\\'s; fine'); SELECT 1;");

        self::assertSame(["INSERT INTO notes VALUES ('it\\'s; fine')", 'SELECT 1'], $statements);
    }
}
