<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Database;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Table;

class AttributeParent extends Model
{
    protected string $table = 'attribute_users';

    protected function setEmail(?string $value): ?string
    {
        return $value === null ? null : strtolower(trim($value));
    }

    protected function getFullName(?string $value): ?string
    {
        return $value === null ? null : ucwords($value);
    }

    protected function getEmail(?string $value): ?string
    {
        return $value === null ? null : strtoupper($value);
    }

    protected function getPosts(mixed $value): mixed
    {
        throw new \LogicException('Loaded relations must bypass hooks');
    }

    protected function setPosts(mixed $value): mixed
    {
        throw new \LogicException('Loaded relations must bypass hooks');
    }
}

class AttributeChild extends AttributeParent {}

final class ModelAttributesTest extends TestCase
{
    public function test_assignment_normalizes_before_dirty_comparison(): void
    {
        $model = (new AttributeChild())->fillRaw(['email' => 'test@example.com'])->syncOriginal();
        $model->email = ' TEST@EXAMPLE.COM ';
        self::assertFalse($model->isDirty());
        self::assertFalse($model->isDirty('email'));
        self::assertSame('test@example.com', $model->getOriginal('email'));
        $model->set('email', ' OTHER@EXAMPLE.COM ');
        self::assertSame(['email' => 'other@example.com'], $model->getDirty());
    }

    public function test_getters_transform_output_without_changing_stored_state(): void
    {
        $model = new AttributeChild(['full_name' => 'jane doe', 'email' => ' A@B.COM ']);
        $model->syncOriginal();
        for ($i = 0; $i < 3; $i++) {
            self::assertSame('Jane Doe', $model->full_name);
            self::assertSame('A@B.COM', $model->get('email'));
            self::assertSame(['full_name' => 'Jane Doe', 'email' => 'A@B.COM'], $model->toArray());
            self::assertSame($model->toArray(), json_decode(json_encode($model, JSON_THROW_ON_ERROR), true));
        }
        self::assertSame(['full_name' => 'jane doe', 'email' => 'a@b.com'], $model->toRawArray());
        self::assertFalse($model->isDirty());
        self::assertSame('jane doe', $model->getOriginal('full_name'));
    }

    public function test_null_missing_and_unhandled_attributes(): void
    {
        $model = new AttributeChild(['email' => null, 'full_name' => null, 'plain' => 42]);
        self::assertNull($model->email);
        self::assertNull($model->full_name);
        self::assertFalse(isset($model->email));
        self::assertSame(42, $model->plain);
        self::assertNull($model->missing);
        self::assertSame('fallback', $model->get('missing', 'fallback'));
        self::assertNull((new AttributeChild())->posts);
        // Framework metadata methods must never be mistaken for getters.
        $model->fill(['table' => 'stored', 'dirty' => 'value', '_includes' => []]);
        self::assertSame('stored', $model->table);
        self::assertSame('value', $model->dirty);
        self::assertArrayNotHasKey('_includes', $model->toArray());
        self::assertSame(['email' => null, 'full_name' => null, 'plain' => 42, 'table' => 'stored', 'dirty' => 'value'], $model->toArray());
    }

    public function test_loaded_relations_win_including_empty_and_null_values(): void
    {
        foreach ([null, [], new AttributeChild(['full_name' => 'jane doe'])] as $value) {
            $model = new AttributeChild();
            $model->setRelation('posts', $value);
            self::assertSame($value, $model->posts);
            self::assertSame($value instanceof Model ? $value->toArray() : $value, $model->toArray()['posts']);
            $model->posts = $value;
            self::assertSame($value, $model->get('posts'));
        }
    }

    public function test_metadata_is_isolated_by_model_class(): void
    {
        $plain = new class extends Model {};
        $plain->fill(['email' => ' A@B.COM ']);
        self::assertSame(' A@B.COM ', $plain->email);
        self::assertSame('A@B.COM', (new AttributeChild(['email' => ' A@B.COM ']))->email);
    }

    public function test_database_hydration_and_persistence_use_raw_values(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        Database::setConnection($connection);
        try {
            $connection->executeStatement('CREATE TABLE attribute_users (id INTEGER PRIMARY KEY, email TEXT, full_name TEXT)');
            $connection->insert('attribute_users', ['email' => ' RAW@EXAMPLE.COM ', 'full_name' => 'jane doe']);
            $table = new class(new AttributeChild()) extends Table { protected bool $timestamps = false; };
            $model = $table->fetchById(1);
            self::assertSame(' RAW@EXAMPLE.COM ', $model->getOriginal('email'));
            self::assertFalse($model->isDirty());
            self::assertSame(1, $model->getPrimaryKeyValue());
            $created = $table->insert(['email' => ' NEW@EXAMPLE.COM ', 'full_name' => 'john smith']);
            self::assertSame('new@example.com', $connection->fetchOne('SELECT email FROM attribute_users WHERE id = ?', [$created->getPrimaryKeyValue()]));
            $fresh = new AttributeChild(['email' => ' SAVE@EXAMPLE.COM ', 'full_name' => 'amy smith']);
            $table->save($fresh);
            self::assertSame('save@example.com', $connection->fetchOne('SELECT email FROM attribute_users WHERE id = ?', [$fresh->getPrimaryKeyValue()]));
            $fresh->email = ' UPDATE@EXAMPLE.COM ';
            $table->save($fresh);
            self::assertSame('update@example.com', $connection->fetchOne('SELECT email FROM attribute_users WHERE id = ?', [$fresh->getPrimaryKeyValue()]));
            // Destroy the table and disconnect: all reads still work without queries.
            $connection->executeStatement('DROP TABLE attribute_users');
            Database::setConnection(null);
            $connection->close();
            self::assertSame('Jane Doe', $model->full_name);
            self::assertSame(' RAW@EXAMPLE.COM ', $model->email);
            self::assertSame('Jane Doe', $model->toArray()['full_name']);
            self::assertNotEmpty(json_encode($model, JSON_THROW_ON_ERROR));
        } finally {
            Database::setConnection(null);
            $connection->close();
        }
    }
}
