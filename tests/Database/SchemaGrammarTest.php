<?php

namespace YasserElgammal\Green\Tests\Database;

use PDO;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Schema\Column;
use YasserElgammal\Green\Database\Schema\Grammar\MySqlGrammar;
use YasserElgammal\Green\Database\Schema\Grammar\SqliteGrammar;

final class SchemaGrammarTest extends TestCase
{
    public function test_mysql_create_compiles_columns_keys_indexes_and_foreign_keys(): void
    {
        $grammar = new MySqlGrammar();
        $statements = $grammar->compileCreate(
            'posts',
            [['action' => 'add', 'column' => Column::bigInteger('id')->autoIncrement()]],
            ['id'],
            [['name' => 'idx_posts_author_id', 'columns' => ['author_id']]],
            [[
                'name' => 'fk_posts_author_id',
                'column' => 'author_id',
                'reference_table' => 'users',
                'reference_column' => 'id',
                'on_delete' => 'CASCADE',
                'on_update' => null,
            ]],
        );

        self::assertStringContainsString('`id` BIGINT AUTO_INCREMENT NOT NULL', $statements[0]);
        self::assertStringContainsString('PRIMARY KEY (`id`)', $statements[0]);
        self::assertStringContainsString('ON DELETE CASCADE', $statements[0]);
        self::assertSame('CREATE INDEX `idx_posts_author_id` ON `posts` (`author_id`)', $statements[1]);
    }

    public function test_sqlite_create_statements_execute_successfully(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $grammar = new SqliteGrammar();
        $statements = $grammar->compileCreate(
            'users',
            [
                ['action' => 'add', 'column' => Column::integer('id')],
                ['action' => 'add', 'column' => Column::string('email')->unique()],
            ],
            ['id'],
            [['name' => 'idx_users_email', 'columns' => ['email']]],
            [],
        );

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        self::assertSame(1, (int) $pdo->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'users'"
        )->fetchColumn());
        self::assertSame('"users"', $grammar->quoteIdentifier('users'));
    }

    public function test_sqlite_drop_all_excludes_internal_sequence_table(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)');

        $statements = (new SqliteGrammar())->compileDropAllTables($pdo);

        self::assertContains('DROP TABLE "users"', $statements);
        self::assertNotContains('DROP TABLE "sqlite_sequence"', $statements);
    }
}
