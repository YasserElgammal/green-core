<?php

namespace YasserElgammal\Green\Tests\Database;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Table;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Relations\RelationRegistry;
use Doctrine\DBAL\DriverManager;

class TestUser extends Model
{
    protected string $table = 'users';
}

class TestUserTable extends Table
{
    protected array $relations = [
        'posts' => [
            'type'        => 'hasMany',
            'model'       => TestPost::class,
            'foreign_key' => 'user_id',
            'local_key'   => 'id',
        ]
    ];
}

class TestPost extends Model
{
    protected string $table = 'posts';
}

class TableTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        // Set up in-memory SQLite database
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        Database::setConnection($this->connection);

        // Create table for test
        $this->connection->executeStatement("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name VARCHAR(255),
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY,
                user_id INTEGER,
                title VARCHAR(255)
            )
        ");
    }

    protected function tearDown(): void
    {
        Database::setConnection(null);
    }

    public function test_relation_loader_singleton()
    {
        $loader1 = RelationRegistry::resolve('hasMany');
        $loader2 = RelationRegistry::resolve('hasMany');

        $this->assertSame($loader1, $loader2);

        $loaderBelongs1 = RelationRegistry::resolve('belongsTo');
        $loaderBelongs2 = RelationRegistry::resolve('belongsTo');

        $this->assertSame($loaderBelongs1, $loaderBelongs2);
        $this->assertNotSame($loader1, $loaderBelongs1);
    }

    public function test_fluent_helpers_when_and_tap()
    {
        $blueprint = new TestUser();
        $table = new TestUserTable($blueprint);

        // Test when with truthy condition
        $applied = false;
        $table->when(true, function ($t) use (&$applied) {
            $applied = true;
        });
        $this->assertTrue($applied);

        // Test when with falsy condition
        $applied = false;
        $table->when(false, function ($t) use (&$applied) {
            $applied = true;
        });
        $this->assertFalse($applied);

        // Test when with default callback
        $defaultCalled = false;
        $table->when(false,
            function ($t) {},
            function ($t) use (&$defaultCalled) {
                $defaultCalled = true;
            }
        );
        $this->assertTrue($defaultCalled);

        // Test tap
        $tapped = false;
        $returnedTable = $table->tap(function ($t) use (&$tapped, $table) {
            $tapped = true;
            $this->assertSame($table, $t);
        });
        $this->assertTrue($tapped);
        $this->assertSame($table, $returnedTable);
    }

    public function test_update_sets_updated_at_automatically(): void
    {
        $this->connection->insert('users', [
            'id' => 1,
            'name' => 'Old Name',
            'created_at' => '2000-01-01 00:00:00',
            'updated_at' => '2000-01-01 00:00:00',
        ]);

        $table = new TestUserTable(new TestUser());

        $affected = $table->update(1, ['name' => 'New Name']);

        $row = $this->connection->fetchAssociative('SELECT name, updated_at FROM users WHERE id = 1');

        $this->assertSame(1, $affected);
        $this->assertSame('New Name', $row['name']);
        $this->assertNotSame('2000-01-01 00:00:00', $row['updated_at']);
        $this->assertNotEmpty($row['updated_at']);
    }

    public function test_insert_sets_created_at_only(): void
    {
        $table = new TestUserTable(new TestUser());

        $user = $table->insert(['name' => 'Inserted User']);

        $row = $this->connection->fetchAssociative('SELECT created_at, updated_at FROM users WHERE id = ?', [$user->id]);

        $this->assertNotEmpty($row['created_at']);
        $this->assertNull($row['updated_at']);
        $this->assertNotEmpty($user->created_at);
        $this->assertNull($user->updated_at);
    }

    public function test_save_new_model_sets_created_at_only(): void
    {
        $table = new TestUserTable(new TestUser());
        $user = new TestUser(['name' => 'Saved User']);

        $saved = $table->save($user);

        $row = $this->connection->fetchAssociative('SELECT created_at, updated_at FROM users WHERE id = ?', [$saved->id]);

        $this->assertNotEmpty($row['created_at']);
        $this->assertNull($row['updated_at']);
        $this->assertNotEmpty($saved->created_at);
        $this->assertNull($saved->updated_at);
    }
}
