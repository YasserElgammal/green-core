<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Tests\Database;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YasserElgammal\Green\Database\Database;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Relations\{BelongsToLoader, HasManyLoader, HasOneLoader, ManyToManyLoader, MorphManyLoader, MorphOneLoader, MorphToLoader, MorphMap};

class RelationAttributeRow extends Model
{
    protected string $table = 'relation_attribute_rows';

    protected function setEmail(string $value): string
    {
        return strtolower(trim($value));
    }
}

class RelationAttributeFormattedRow extends RelationAttributeRow
{
    protected function getParentId(int $value): int
    {
        return $value + 100;
    }
}

class RelationAttributeParent extends Model {}

class RelationAttributeFormattedParent extends RelationAttributeParent
{
    protected function getId(int $value): int
    {
        return $value + 100;
    }

    protected function getParentId(int $value): int
    {
        return $value + 100;
    }

    protected function getTargetType(string $value): string
    {
        return 'display-' . $value;
    }
}

final class RelationAttributesTest extends TestCase
{
    public static function loaders(): iterable
    {
        foreach ([HasManyLoader::class, HasOneLoader::class, BelongsToLoader::class,
            ManyToManyLoader::class, MorphManyLoader::class, MorphOneLoader::class,
            MorphToLoader::class] as $loader) {
            yield $loader . ' hydration' => [$loader, false];
            yield $loader . ' raw keys' => [$loader, true];
        }
    }

    #[DataProvider('loaders')]
    public function test_relations_use_raw_keys_and_preserve_persisted_values(string $loaderClass, bool $formatted): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        Database::setConnection($connection);
        $previousMorphMap = MorphMap::all();
        try {
            $connection->executeStatement('CREATE TABLE relation_attribute_rows (id INTEGER PRIMARY KEY, parent_id INTEGER, target_type TEXT, email TEXT)');
            $connection->executeStatement('CREATE TABLE relation_attribute_pivot (parent_id INTEGER, related_id INTEGER)');
            $connection->insert('relation_attribute_rows', ['id' => 1, 'parent_id' => 1, 'target_type' => 'attribute-parent', 'email' => ' RAW@EXAMPLE.COM ']);
            // A real competing record catches accidental attachment through a formatted ID.
            $connection->insert('relation_attribute_rows', ['id' => 101, 'parent_id' => 101, 'target_type' => 'attribute-parent', 'email' => 'wrong@example.com']);
            $connection->insert('relation_attribute_pivot', ['parent_id' => 1, 'related_id' => 1]);
            $connection->insert('relation_attribute_pivot', ['parent_id' => 101, 'related_id' => 101]);
            $parent = $formatted ? new RelationAttributeFormattedParent() : new RelationAttributeParent();
            $parent->fillRaw(['id' => 1, 'parent_id' => 1, 'target_type' => 'attribute-row'])->syncOriginal();
            $relatedClass = $formatted ? RelationAttributeFormattedRow::class : RelationAttributeRow::class;
            MorphMap::register(['attribute-row' => $relatedClass]);
            $config = [
                'model' => $relatedClass,
                'foreign_key' => 'parent_id',
                'local_key' => 'id',
                'owner_key' => 'id',
                'pivot' => 'relation_attribute_pivot',
                'related_key' => 'related_id',
                'type_column' => 'target_type',
                'id_column' => 'parent_id',
                'morph_type' => 'attribute-parent',
            ];
            (new $loaderClass())->load([$parent], 'related', $config);
            $attached = $parent->related;
            if (is_array($attached)) {
                self::assertCount(1, $attached);
                $attached = $attached[0];
            }
            self::assertInstanceOf(Model::class, $attached);
            self::assertSame(1, $attached->getPrimaryKeyValue());
            self::assertSame(' RAW@EXAMPLE.COM ', $attached->toRawArray()['email']);
            // The setter still runs on explicit application assignment after hydration.
            $attached->email = ' NEXT@EXAMPLE.COM ';
            self::assertSame('next@example.com', $attached->toRawArray()['email']);
            if ($formatted) {
                self::assertSame(101, $parent->id);
                self::assertSame(101, $attached->parent_id);
            }
        } finally {
            MorphMap::reset();
            MorphMap::register($previousMorphMap);
            Database::setConnection(null);
            $connection->close();
        }
    }
}
