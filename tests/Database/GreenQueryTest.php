<?php

namespace YasserElgammal\Green\Tests\Database;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Table;

class GreenQueryUser extends Model
{
    protected string $table = 'users';
}

/** @extends Table<GreenQueryUser> */
class GreenQueryUserTable extends Table
{
    protected array $relations = [
        'posts' => [
            'type'        => 'hasMany',
            'model'       => GreenQueryPost::class,
            'foreign_key' => 'user_id',
            'local_key'   => 'id',
        ],
    ];
}

class GreenQueryPost extends Model
{
    protected string $table = 'posts';
}

class GreenQueryPostTable extends Table
{
}

class GreenQueryTest extends TestCase
{
    private $connection;
    private GreenQueryUserTable $users;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        Database::setConnection($this->connection);

        $this->connection->executeStatement('
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255),
                email VARCHAR(255),
                status VARCHAR(50),
                role VARCHAR(50),
                age INTEGER,
                score INTEGER,
                deleted_at DATETIME NULL,
                created_at DATETIME
            )
        ');

        $this->connection->executeStatement('
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                title VARCHAR(255)
            )
        ');

        $this->seedUsers();
        $this->users = new GreenQueryUserTable(new GreenQueryUser());
    }

    protected function tearDown(): void
    {
        Database::setConnection(null);
    }

    public function test_fetches_with_flexible_where_and_sorting(): void
    {
        $result = $this->users->query()
            ->where('status', 'active')
            ->where('age', '>=', 18)
            ->latest()
            ->fetch();

        $this->assertCount(2, $result);
        $this->assertSame('Omar', $result[0]->name);
        $this->assertSame('Mona', $result[1]->name);
    }

    public function test_accepts_array_where_and_first_required(): void
    {
        $user = $this->users->query()
            ->where(['status' => 'active', 'role' => 'admin'])
            ->firstRequired();

        $this->assertSame('Mona', $user->name);
    }

    public function test_groups_nested_or_conditions(): void
    {
        $result = $this->users->query()
            ->where('status', 'active')
            ->whereGroup(fn ($query) => $query
                ->where('role', 'admin')
                ->orWhere('score', '>=', 90)
            )
            ->oldest('id')
            ->fetch();

        $this->assertCount(2, $result);
        $this->assertSame(['Mona', 'Omar'], array_map(fn ($user) => $user->name, $result));
    }

    public function test_where_helpers(): void
    {
        $result = $this->users->query()
            ->whereIn('role', ['admin', 'editor'])
            ->whereNull('deleted_at')
            ->whereBetween('age', 18, 40)
            ->whereLike('email', '%@green.dev')
            ->oldest('id')
            ->fetch();

        $this->assertSame(['Mona', 'Omar'], array_map(fn ($user) => $user->name, $result));
    }

    public function test_or_condition_helpers(): void
    {
        $result = $this->users->query()
            ->where('status', 'inactive')
            ->orWhereGroup(fn ($query) => $query
                ->whereIn('role', ['editor'])
                ->whereBetween('age', 30, 40)
            )
            ->oldest('id')
            ->fetch();

        $this->assertSame(['Omar', 'Lina'], array_map(fn ($user) => $user->name, $result));
    }

    public function test_negative_condition_helpers(): void
    {
        $result = $this->users->query()
            ->whereNotIn('role', ['member'])
            ->whereNotBetween('age', 40, 99)
            ->whereNotLike('email', '%example.com')
            ->whereNotNull('created_at')
            ->oldest('id')
            ->fetch();

        $this->assertSame(['Mona', 'Omar'], array_map(fn ($user) => $user->name, $result));
    }

    public function test_or_null_and_empty_list_helpers(): void
    {
        $result = $this->users->query()
            ->whereIn('role', [])
            ->orWhereNull('deleted_at')
            ->whereNotIn('id', [])
            ->oldest('id')
            ->fetch();

        $this->assertSame(['Mona', 'Omar', 'Lina'], array_map(fn ($user) => $user->name, $result));
    }

    public function test_or_like_and_or_not_helpers(): void
    {
        $result = $this->users->query()
            ->where('role', 'missing')
            ->orWhereLike('email', 'mona%')
            ->orWhereNotIn('role', ['admin', 'editor', 'member'])
            ->orWhereNotBetween('age', 18, 99)
            ->orWhereNotNull('deleted_at')
            ->oldest('id')
            ->fetch();

        $this->assertSame(['Mona', 'Tarek'], array_map(fn ($user) => $user->name, $result));
    }

    public function test_limit_offset_and_first(): void
    {
        $user = $this->users->query()
            ->oldest('id')
            ->offset(1)
            ->limit(1)
            ->first();

        $this->assertNotNull($user);
        $this->assertSame('Omar', $user->name);
    }

    public function test_aggregations_respect_where_conditions(): void
    {
        $query = $this->users->query()->where('status', 'active')->whereNull('deleted_at');

        $this->assertSame(2, $query->count());
        $this->assertTrue($this->users->query()->where('role', 'admin')->exists());
        $this->assertSame(155, $this->users->query()->where('status', 'active')->whereNull('deleted_at')->sum('score'));
        $this->assertSame(77.5, $this->users->query()->where('status', 'active')->whereNull('deleted_at')->avg('score'));
        $this->assertSame(65, (int) $this->users->query()->where('status', 'active')->whereNull('deleted_at')->min('score'));
        $this->assertSame(90, (int) $this->users->query()->where('status', 'active')->whereNull('deleted_at')->max('score'));
    }

    public function test_include_before_query_fetch_loads_relations(): void
    {
        $this->connection->insert('posts', ['user_id' => 1, 'title' => 'First']);
        $this->connection->insert('posts', ['user_id' => 1, 'title' => 'Second']);

        $users = $this->users
            ->include('posts')
            ->query()
            ->where('id', 1)
            ->fetch();

        $this->assertCount(1, $users);
        $this->assertCount(2, $users[0]->posts);
        $this->assertSame('First', $users[0]->posts[0]->title);
    }

    public function test_table_aliases(): void
    {
        $this->assertCount(4, $this->users->all());
        $this->assertSame('Mona', $this->users->find(1)->name);
        $this->assertSame('Mona', $this->users->findRequired(1)->name);
    }

    public function test_transaction_commits_and_returns_callback_value(): void
    {
        $id = Database::transaction(function () {
            $this->connection->insert('users', [
                'name'       => 'Committed',
                'email'      => 'committed@green.dev',
                'status'     => 'active',
                'role'       => 'member',
                'age'        => 22,
                'score'      => 40,
                'created_at' => '2026-01-05 00:00:00',
            ]);

            return (int) $this->connection->lastInsertId();
        });

        $this->assertSame('Committed', $this->users->find($id)->name);
    }

    public function test_transaction_rolls_back_on_exception(): void
    {
        try {
            Database::transaction(function () {
                $this->connection->insert('users', [
                    'name'       => 'Rolled Back',
                    'email'      => 'rollback@green.dev',
                    'status'     => 'active',
                    'role'       => 'member',
                    'age'        => 22,
                    'score'      => 40,
                    'created_at' => '2026-01-06 00:00:00',
                ]);

                throw new \RuntimeException('Stop');
            });
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertFalse($this->users->query()->where('email', 'rollback@green.dev')->exists());
    }

    public function test_first_required_throws_when_no_row_exists(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->users->query()
            ->where('email', 'missing@green.dev')
            ->firstRequired();
    }

    private function seedUsers(): void
    {
        $rows = [
            ['name' => 'Mona', 'email' => 'mona@green.dev', 'status' => 'active', 'role' => 'admin', 'age' => 29, 'score' => 65, 'deleted_at' => null, 'created_at' => '2026-01-01 00:00:00'],
            ['name' => 'Omar', 'email' => 'omar@green.dev', 'status' => 'active', 'role' => 'editor', 'age' => 35, 'score' => 90, 'deleted_at' => null, 'created_at' => '2026-01-02 00:00:00'],
            ['name' => 'Lina', 'email' => 'lina@green.dev', 'status' => 'inactive', 'role' => 'admin', 'age' => 41, 'score' => 80, 'deleted_at' => null, 'created_at' => '2026-01-03 00:00:00'],
            ['name' => 'Tarek', 'email' => 'tarek@example.com', 'status' => 'active', 'role' => 'member', 'age' => 17, 'score' => 50, 'deleted_at' => '2026-01-04 00:00:00', 'created_at' => '2026-01-04 00:00:00'],
        ];

        foreach ($rows as $row) {
            $this->connection->insert('users', $row);
        }
    }
}