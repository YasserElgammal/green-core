<?php

namespace YasserElgammal\Green\Tests\Database;

use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Table;
use YasserElgammal\Green\Database\Relations\HasMany;
use Doctrine\DBAL\DriverManager;

// ─── Test fixtures ───────────────────────────────────────────────────────────

class ChunkUser extends Model
{
    protected string $table = 'users';
}

class ChunkPost extends Model
{
    protected string $table = 'posts';
}

class ChunkUserTable extends Table
{
    protected function relations(): array
    {
        return [
            'posts' => new HasMany(ChunkPost::class, foreignKey: 'user_id', localKey: 'id'),
        ];
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

class ChunkedProcessingTest extends TestCase
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

    private function seedUsers(int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->connection->insert('users', [
                'id'         => $i,
                'name'       => "User {$i}",
                'created_at' => '2024-01-01 00:00:00',
                'updated_at' => null,
            ]);
        }
    }

    // ─── chunk() ──────────────────────────────────────────────────────────────

    public function test_chunk_processes_all_rows_in_batches(): void
    {
        $this->seedUsers(7);

        $table = new ChunkUserTable(new ChunkUser());
        $collected = [];

        $table->chunk(3, function (array $models, int $page) use (&$collected) {
            foreach ($models as $model) {
                $collected[] = $model->name;
            }
        });

        $this->assertCount(7, $collected);
        $this->assertSame('User 1', $collected[0]);
        $this->assertSame('User 7', $collected[6]);
    }

    public function test_chunk_with_exact_multiple_of_size(): void
    {
        $this->seedUsers(6);

        $table = new ChunkUserTable(new ChunkUser());
        $pages = [];

        $table->chunk(3, function (array $models, int $page) use (&$pages) {
            $pages[] = $page;
        });

        // 6 rows / 3 per chunk = exactly 2 chunks
        $this->assertSame([1, 2], $pages);
    }

    public function test_chunk_stops_when_callback_returns_false(): void
    {
        $this->seedUsers(10);

        $table = new ChunkUserTable(new ChunkUser());
        $processedPages = [];

        $table->chunk(3, function (array $models, int $page) use (&$processedPages) {
            $processedPages[] = $page;
            if ($page === 2) {
                return false; // stop after page 2
            }
        });

        $this->assertSame([1, 2], $processedPages);
    }

    public function test_chunk_provides_correct_page_numbers(): void
    {
        $this->seedUsers(5);

        $table = new ChunkUserTable(new ChunkUser());
        $pages = [];

        $table->chunk(2, function (array $models, int $page) use (&$pages) {
            $pages[] = $page;
        });

        $this->assertSame([1, 2, 3], $pages);
    }

    public function test_chunk_with_empty_table_does_nothing(): void
    {
        $table = new ChunkUserTable(new ChunkUser());
        $called = false;

        $table->chunk(10, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function test_chunk_returns_hydrated_models(): void
    {
        $this->seedUsers(2);

        $table = new ChunkUserTable(new ChunkUser());
        $models = [];

        $table->chunk(10, function (array $batch) use (&$models) {
            $models = $batch;
        });

        $this->assertCount(2, $models);
        $this->assertInstanceOf(Model::class, $models[0]);
        $this->assertSame(1, $models[0]->id);
        $this->assertSame('User 1', $models[0]->name);
    }

    public function test_chunk_loads_includes_per_batch(): void
    {
        $this->seedUsers(3);
        $this->connection->insert('posts', ['id' => 1, 'user_id' => 1, 'title' => 'Post A']);
        $this->connection->insert('posts', ['id' => 2, 'user_id' => 2, 'title' => 'Post B']);

        $table = new ChunkUserTable(new ChunkUser());
        $allUsers = [];

        $table->include('posts')->chunk(2, function (array $batch) use (&$allUsers) {
            foreach ($batch as $u) {
                $allUsers[] = $u;
            }
        });

        // User 1 has 1 post
        $this->assertCount(1, $allUsers[0]->posts);
        $this->assertSame('Post A', $allUsers[0]->posts[0]->title);

        // User 2 has 1 post
        $this->assertCount(1, $allUsers[1]->posts);

        // User 3 has no posts
        $this->assertEmpty($allUsers[2]->posts);
    }

    public function test_chunk_throws_for_zero_size(): void
    {
        $table = new ChunkUserTable(new ChunkUser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk size must be at least 1');

        $table->chunk(0, function () {});
    }

    public function test_chunk_throws_for_negative_size(): void
    {
        $table = new ChunkUserTable(new ChunkUser());

        $this->expectException(\InvalidArgumentException::class);

        $table->chunk(-5, function () {});
    }

    public function test_chunk_with_select(): void
    {
        $this->seedUsers(3);

        $table = new ChunkUserTable(new ChunkUser());
        $collected = [];

        $table->select('name')->chunk(2, function (array $models) use (&$collected) {
            foreach ($models as $model) {
                $collected[] = $model;
            }
        });

        $this->assertCount(3, $collected);
        // Primary key auto-injected
        $this->assertSame(1, $collected[0]->id);
        $this->assertSame('User 1', $collected[0]->name);
        // Non-selected columns should be null
        $this->assertNull($collected[0]->created_at);
    }

    // ─── chunkById() ──────────────────────────────────────────────────────────

    public function test_chunk_by_id_processes_all_rows(): void
    {
        $this->seedUsers(7);

        $table = new ChunkUserTable(new ChunkUser());
        $collected = [];

        $table->chunkById(3, function (array $models) use (&$collected) {
            foreach ($models as $model) {
                $collected[] = $model->id;
            }
        });

        $this->assertSame([1, 2, 3, 4, 5, 6, 7], $collected);
    }

    public function test_chunk_by_id_stops_when_callback_returns_false(): void
    {
        $this->seedUsers(10);

        $table = new ChunkUserTable(new ChunkUser());
        $collected = [];

        $table->chunkById(3, function (array $models) use (&$collected) {
            foreach ($models as $model) {
                $collected[] = $model->id;
            }
            // Stop after first chunk
            return false;
        });

        $this->assertSame([1, 2, 3], $collected);
    }

    public function test_chunk_by_id_with_empty_table(): void
    {
        $table = new ChunkUserTable(new ChunkUser());
        $called = false;

        $table->chunkById(10, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function test_chunk_by_id_loads_includes_per_batch(): void
    {
        $this->seedUsers(2);
        $this->connection->insert('posts', ['id' => 1, 'user_id' => 1, 'title' => 'Hello']);

        $table = new ChunkUserTable(new ChunkUser());
        $allUsers = [];

        $table->include('posts')->chunkById(1, function (array $batch) use (&$allUsers) {
            foreach ($batch as $u) {
                $allUsers[] = $u;
            }
        });

        $this->assertCount(2, $allUsers);
        $this->assertCount(1, $allUsers[0]->posts);
        $this->assertEmpty($allUsers[1]->posts);
    }

    public function test_chunk_by_id_with_non_contiguous_ids(): void
    {
        // Insert rows with gaps in IDs
        $this->connection->insert('users', ['id' => 5,  'name' => 'A', 'created_at' => null, 'updated_at' => null]);
        $this->connection->insert('users', ['id' => 10, 'name' => 'B', 'created_at' => null, 'updated_at' => null]);
        $this->connection->insert('users', ['id' => 20, 'name' => 'C', 'created_at' => null, 'updated_at' => null]);
        $this->connection->insert('users', ['id' => 50, 'name' => 'D', 'created_at' => null, 'updated_at' => null]);

        $table = new ChunkUserTable(new ChunkUser());
        $collected = [];

        $table->chunkById(2, function (array $models) use (&$collected) {
            foreach ($models as $model) {
                $collected[] = $model->id;
            }
        });

        $this->assertSame([5, 10, 20, 50], $collected);
    }

    public function test_chunk_by_id_throws_for_zero_size(): void
    {
        $table = new ChunkUserTable(new ChunkUser());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chunk size must be at least 1');

        $table->chunkById(0, function () {});
    }

    public function test_chunk_by_id_with_exact_multiple(): void
    {
        $this->seedUsers(6);

        $table = new ChunkUserTable(new ChunkUser());
        $chunkCount = 0;

        $table->chunkById(3, function (array $models) use (&$chunkCount) {
            $chunkCount++;
        });

        // 6 rows / 3 per chunk = 2 full chunks
        $this->assertSame(2, $chunkCount);
    }

    // ─── chunkFromBuilder / chunkByIdFromBuilder ──────────────────────────────

    public function test_chunk_from_builder_with_where_clause(): void
    {
        $this->seedUsers(5);

        $table = new ChunkUserTable(new ChunkUser());
        $qb = $table->builder()->where('id > 2');

        $collected = [];
        $table->chunkFromBuilder($qb, 2, function (array $models, int $page) use (&$collected) {
            foreach ($models as $m) {
                $collected[] = $m->id;
            }
        });

        // Only ids 3, 4, 5 should be processed
        $this->assertSame([3, 4, 5], $collected);
    }

    public function test_chunk_by_id_from_builder_with_where_clause(): void
    {
        $this->seedUsers(5);

        $table = new ChunkUserTable(new ChunkUser());
        $qb = $table->builder()->where('id <= 4');

        $collected = [];
        $table->chunkByIdFromBuilder($qb, 2, function (array $models) use (&$collected) {
            foreach ($models as $m) {
                $collected[] = $m->id;
            }
        });

        // Only ids 1, 2, 3, 4 should be processed
        $this->assertSame([1, 2, 3, 4], $collected);
    }

    public function test_chunk_size_of_one_processes_row_by_row(): void
    {
        $this->seedUsers(3);

        $table = new ChunkUserTable(new ChunkUser());
        $chunkSizes = [];

        $table->chunk(1, function (array $models, int $page) use (&$chunkSizes) {
            $chunkSizes[] = count($models);
        });

        $this->assertSame([1, 1, 1], $chunkSizes);
    }

    public function test_chunk_by_id_size_of_one_processes_row_by_row(): void
    {
        $this->seedUsers(3);

        $table = new ChunkUserTable(new ChunkUser());
        $chunkSizes = [];

        $table->chunkById(1, function (array $models) use (&$chunkSizes) {
            $chunkSizes[] = count($models);
        });

        $this->assertSame([1, 1, 1], $chunkSizes);
    }
}
