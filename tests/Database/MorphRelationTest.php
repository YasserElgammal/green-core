<?php

namespace YasserElgammal\Green\Tests\Database;

use PHPUnit\Framework\TestCase;
use Doctrine\DBAL\DriverManager;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Table;
use YasserElgammal\Green\Database\Attributes\MorphAlias;
use YasserElgammal\Green\Database\Relations\MorphMap;
use YasserElgammal\Green\Database\Relations\MorphMany;
use YasserElgammal\Green\Database\Relations\MorphTo;
use YasserElgammal\Green\Database\Relations\MorphOne;

// ─── Test Models (with #[MorphAlias] attributes) ─────────────────────────

#[MorphAlias('post')]
class MorphPost extends Model
{
    protected string $table = 'posts';
}

#[MorphAlias('video')]
class MorphVideo extends Model
{
    protected string $table = 'videos';
}

class MorphComment extends Model
{
    protected string $table = 'comments';
}

class MorphImage extends Model
{
    protected string $table = 'images';
}

// ─── Test Tables ──────────────────────────────────────────────────────────

class MorphPostTable extends Table
{
    public function __construct()
    {
        parent::__construct(new MorphPost());
    }

    protected function relations(): array
    {
        return [
            'comments' => new MorphMany(MorphComment::class, 'commentable'),
            'image'    => new MorphOne(MorphImage::class, 'imageable'),
        ];
    }
}

class MorphVideoTable extends Table
{
    public function __construct()
    {
        parent::__construct(new MorphVideo());
    }

    protected function relations(): array
    {
        return [
            'comments' => new MorphMany(MorphComment::class, 'commentable'),
            'image'    => new MorphOne(MorphImage::class, 'imageable'),
        ];
    }
}

class MorphCommentTable extends Table
{
    public function __construct()
    {
        parent::__construct(new MorphComment());
    }

    protected function relations(): array
    {
        return [
            'commentable' => new MorphTo('commentable', models: [
                MorphPost::class,
                MorphVideo::class,
            ]),
        ];
    }
}

class MorphImageTable extends Table
{
    public function __construct()
    {
        parent::__construct(new MorphImage());
    }

    protected function relations(): array
    {
        return [
            'imageable' => new MorphTo('imageable', models: [
                MorphPost::class,
                MorphVideo::class,
            ]),
        ];
    }
}

// ─── Test Case ────────────────────────────────────────────────────────────

class MorphRelationTest extends TestCase
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
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY,
                title VARCHAR(255)
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE videos (
                id INTEGER PRIMARY KEY,
                title VARCHAR(255)
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY,
                body TEXT,
                commentable_type VARCHAR(255),
                commentable_id INTEGER
            )
        ");

        $this->connection->executeStatement("
            CREATE TABLE images (
                id INTEGER PRIMARY KEY,
                url VARCHAR(255),
                imageable_type VARCHAR(255),
                imageable_id INTEGER
            )
        ");
    }

    protected function tearDown(): void
    {
        Database::setConnection(null);
        MorphMap::reset();
    }

    // ─── Attribute Discovery ─────────────────────────────────────────────

    public function test_morph_alias_attribute_is_read_automatically()
    {
        $alias = MorphMap::alias(MorphPost::class);
        $this->assertEquals('post', $alias);
    }

    public function test_morph_alias_attribute_caches_on_first_read()
    {
        MorphMap::alias(MorphPost::class);
        $this->assertEquals(MorphPost::class, MorphMap::resolve('post'));
    }

    public function test_explicit_register_overrides_attribute()
    {
        MorphMap::register(['blog_post' => MorphPost::class]);
        $this->assertEquals('blog_post', MorphMap::alias(MorphPost::class));
    }

    public function test_morph_map_throws_on_unknown_alias()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown morph type [unknown]');
        MorphMap::resolve('unknown');
    }

    public function test_morph_map_throws_on_class_without_attribute()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No morph alias registered for');
        MorphMap::alias(MorphComment::class);
    }

    // ─── MorphMany (attribute-based) ─────────────────────────────────────

    public function test_morph_many_loads_correct_children_via_attribute()
    {
        $this->connection->insert('posts', ['id' => 1, 'title' => 'Post 1']);
        $this->connection->insert('posts', ['id' => 2, 'title' => 'Post 2']);
        $this->connection->insert('videos', ['id' => 1, 'title' => 'Video 1']);

        $this->connection->insert('comments', ['id' => 1, 'body' => 'A', 'commentable_type' => 'post', 'commentable_id' => 1]);
        $this->connection->insert('comments', ['id' => 2, 'body' => 'B', 'commentable_type' => 'post', 'commentable_id' => 1]);
        $this->connection->insert('comments', ['id' => 3, 'body' => 'C', 'commentable_type' => 'video', 'commentable_id' => 1]);

        $postTable = new MorphPostTable();
        $posts = $postTable->include('comments')->fetchAll();

        $this->assertCount(2, $posts);
        $this->assertCount(2, $posts[0]->comments);
        $this->assertEquals('A', $posts[0]->comments[0]->body);
        $this->assertEquals('B', $posts[0]->comments[1]->body);
        $this->assertCount(0, $posts[1]->comments);

        $videoTable = new MorphVideoTable();
        $videos = $videoTable->include('comments')->fetchAll();
        $this->assertCount(1, $videos[0]->comments);
        $this->assertEquals('C', $videos[0]->comments[0]->body);
    }

    // ─── MorphOne (attribute-based) ──────────────────────────────────────

    public function test_morph_one_loads_single_child_via_attribute()
    {
        $this->connection->insert('posts', ['id' => 1, 'title' => 'Post 1']);
        $this->connection->insert('posts', ['id' => 2, 'title' => 'Post 2']);

        // Only one image per post
        $this->connection->insert('images', ['id' => 1, 'url' => 'post1.jpg', 'imageable_type' => 'post', 'imageable_id' => 1]);
        $this->connection->insert('images', ['id' => 2, 'url' => 'post2.jpg', 'imageable_type' => 'post', 'imageable_id' => 2]);
        // A second image for post 1, should be ignored (or just returns the first one)
        $this->connection->insert('images', ['id' => 3, 'url' => 'post1_extra.jpg', 'imageable_type' => 'post', 'imageable_id' => 1]);

        $postTable = new MorphPostTable();
        $posts = $postTable->include('image')->fetchAll();

        $this->assertCount(2, $posts);
        
        $this->assertInstanceOf(MorphImage::class, $posts[0]->image);
        $this->assertEquals('post1.jpg', $posts[0]->image->url);

        $this->assertInstanceOf(MorphImage::class, $posts[1]->image);
        $this->assertEquals('post2.jpg', $posts[1]->image->url);
    }

    // ─── MorphTo (attribute-based) ───────────────────────────────────────

    public function test_morph_to_loads_correct_parents_via_attribute()
    {
        $this->connection->insert('posts', ['id' => 10, 'title' => 'Post 10']);
        $this->connection->insert('posts', ['id' => 15, 'title' => 'Post 15']);
        $this->connection->insert('videos', ['id' => 7, 'title' => 'Video 7']);

        $this->connection->insert('comments', ['id' => 1, 'body' => 'A', 'commentable_type' => 'post', 'commentable_id' => 10]);
        $this->connection->insert('comments', ['id' => 2, 'body' => 'B', 'commentable_type' => 'post', 'commentable_id' => 15]);
        $this->connection->insert('comments', ['id' => 3, 'body' => 'C', 'commentable_type' => 'video', 'commentable_id' => 7]);
        $this->connection->insert('comments', ['id' => 4, 'body' => 'D', 'commentable_type' => 'post', 'commentable_id' => 10]);

        $commentTable = new MorphCommentTable();
        $comments = $commentTable->include('commentable')->fetchAll();

        $this->assertCount(4, $comments);

        $this->assertInstanceOf(MorphPost::class, $comments[0]->commentable);
        $this->assertEquals(10, $comments[0]->commentable->id);

        $this->assertInstanceOf(MorphPost::class, $comments[1]->commentable);
        $this->assertEquals(15, $comments[1]->commentable->id);

        $this->assertInstanceOf(MorphVideo::class, $comments[2]->commentable);
        $this->assertEquals(7, $comments[2]->commentable->id);

        $this->assertInstanceOf(MorphPost::class, $comments[3]->commentable);
        $this->assertEquals(10, $comments[3]->commentable->id);
    }

    public function test_morph_to_handles_null_keys()
    {
        $this->connection->insert('comments', ['id' => 1, 'body' => 'A', 'commentable_type' => null, 'commentable_id' => null]);

        $commentTable = new MorphCommentTable();
        $comments = $commentTable->include('commentable')->fetchAll();

        $this->assertCount(1, $comments);
        $this->assertNull($comments[0]->commentable);
    }

    public function test_morph_to_throws_on_unknown_type()
    {
        $this->connection->insert('comments', ['id' => 1, 'body' => 'A', 'commentable_type' => 'unknown_type', 'commentable_id' => 1]);

        $commentTable = new MorphCommentTable();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown morph type [unknown_type]');

        $commentTable->include('commentable')->fetchAll();
    }
}
