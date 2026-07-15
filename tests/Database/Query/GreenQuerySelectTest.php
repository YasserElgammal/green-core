<?php

namespace YasserElgammal\Green\Tests\Database\Query;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Table;
use YasserElgammal\Green\Database\Model;
use Doctrine\DBAL\DriverManager;

class SelectTestUser extends Model
{
    protected string $table = 'users';
}

class SelectTestUserTable extends Table
{
}

class GreenQuerySelectTest extends TestCase
{
    private $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        Database::setConnection($this->connection);

        $this->connection->executeStatement("
            CREATE TABLE users (
                id INTEGER PRIMARY KEY,
                name VARCHAR(255),
                email VARCHAR(255),
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        // Seed test data
        $this->connection->insert('users', [
            'id' => 1,
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => null,
        ]);
        $this->connection->insert('users', [
            'id' => 2,
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'created_at' => '2024-01-02 00:00:00',
            'updated_at' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Database::setConnection(null);
    }

    public function test_query_select_with_fetch(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());
        $users = $table->query()
            ->select('name', 'email')
            ->fetch();

        $this->assertCount(2, $users);
        $this->assertSame(1, $users[0]->id); // PK auto-injected
        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('alice@example.com', $users[0]->email);
        $this->assertNull($users[0]->created_at);
    }

    public function test_query_select_with_array_syntax(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());
        $users = $table->query()
            ->select(['name', 'email'])
            ->fetch();

        $this->assertCount(2, $users);
        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('alice@example.com', $users[0]->email);
        $this->assertNull($users[0]->created_at);
    }

    public function test_query_select_with_first(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());
        $user = $table->query()
            ->select('name')
            ->where('id', 1)
            ->first();

        $this->assertNotNull($user);
        $this->assertSame(1, $user->id);
        $this->assertSame('Alice', $user->name);
        $this->assertNull($user->email);
        $this->assertNull($user->created_at);
    }

    public function test_query_select_combined_with_where(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());
        $users = $table->query()
            ->select('name')
            ->where('name', 'Bob')
            ->fetch();

        $this->assertCount(1, $users);
        $this->assertSame(2, $users[0]->id);
        $this->assertSame('Bob', $users[0]->name);
        $this->assertNull($users[0]->email);
    }

    public function test_query_select_with_pagination(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());
        $result = $table->query()
            ->select('name')
            ->paginate(1, 1);

        $this->assertCount(1, $result['data']);
        $this->assertSame(1, $result['data'][0]->id);
        $this->assertSame('Alice', $result['data'][0]->name);
        $this->assertNull($result['data'][0]->email);
        $this->assertSame(2, $result['meta']['total_items']);
    }

    public function test_query_without_select_returns_all_columns(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());
        $users = $table->query()->fetch();

        $this->assertCount(2, $users);
        $this->assertSame('Alice', $users[0]->name);
        $this->assertSame('alice@example.com', $users[0]->email);
        $this->assertSame('2024-01-01 00:00:00', $users[0]->created_at);
    }

    public function test_query_select_invalid_column_throws(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());

        $this->expectException(\InvalidArgumentException::class);
        $table->query()->select('invalid column!');
    }

    public function test_query_select_auto_injects_pk(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());
        $users = $table->query()
            ->select('email')
            ->fetch();

        // PK 'id' should be auto-injected even though not specified
        $this->assertSame(1, $users[0]->id);
        $this->assertSame('alice@example.com', $users[0]->email);
        $this->assertNull($users[0]->name);
    }

    public function test_query_select_with_order(): void
    {
        $table = new SelectTestUserTable(new SelectTestUser());
        $users = $table->query()
            ->select('name')
            ->orderBy('name', 'desc')
            ->fetch();

        $this->assertCount(2, $users);
        $this->assertSame('Bob', $users[0]->name);
        $this->assertSame('Alice', $users[1]->name);
    }
}
