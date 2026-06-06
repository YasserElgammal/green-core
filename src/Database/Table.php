<?php

namespace YasserElgammal\Green\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AggregationLoader;
use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AggregationRegistry;
use YasserElgammal\Green\Database\IncludeQuery\IncludeQueryEngine;
use YasserElgammal\Green\Database\IncludeQuery\Resolver\ResolvedAggregation;
use YasserElgammal\Green\Database\IncludeQuery\Resolver\ResolvedInclude;
use YasserElgammal\Green\Database\Relations\RelationRegistry;
use YasserElgammal\Green\Pagination\Paginator;

/**
 * Table is the Table Gateway — it handles all DB operations
 * for a given Model type and returns hydrated Model instances.
 *
 * Design Patterns:
 *   - Table Gateway (Fowler - PoEAA)
 *   - Data Mapper  (hydration)
 *   - Fluent Interface (query building + include chaining)
 *   - Strategy  (relation loaders via RelationRegistry)
 */
class Table
{
    private Connection $connection;
    private string $table;
    private string $primaryKey;

    /**
     * Pending eager-load relation names (supports dot-notation).
     *
     * @var string[]
     */
    private array $pendingIncludes = [];

    /**
     * Pending IQL resolved includes (from advanced syntax).
     *
     * @var ResolvedInclude[]
     */
    private array $pendingResolved = [];

    /**
     * Pending aggregation requests (from programmatic API).
     *
     * @var ResolvedAggregation[]
     */
    private array $pendingAggregations = [];

    /**
     * Relation registry defined by subclasses.
     *
     * Format:
     * [
     *   'posts' => [
     *       'type'        => 'hasMany',
     *       'model'       => Post::class,
     *       'foreign_key' => 'user_id',
     *       'local_key'   => 'id',
     *   ],
     *   'roles' => [
     *       'type'        => 'manyToMany',
     *       'model'       => Role::class,
     *       'pivot'       => 'user_roles',
     *       'foreign_key' => 'user_id',
     *       'related_key' => 'role_id',
     *       'local_key'   => 'id',
     *   ],
     * ]
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $relations = [];

    public function __construct(private readonly Model $blueprint)
    {
        $this->connection  = Database::getConnection();
        $this->table       = $blueprint->getTable();
        $this->primaryKey  = $blueprint->getPrimaryKey();
    }

    // ─── Aggregation API ──────────────────────────────────────────────────────

    /**
     * Queue a COUNT aggregation on one or more relations.
     *
     * Usage:
     *   ->includeCount('comments')
     *   ->includeCount(['comments', 'likes'])
     *
     * @param  string|string[]  $relations
     * @return static
     */
    public function includeCount(string|array $relations): static
    {
        return $this->queueAggregation('count', $relations);
    }

    /**
     * Queue an EXISTS aggregation on one or more relations.
     *
     * Usage:
     *   ->includeExists('comments')
     *   ->includeExists(['comments', 'likes'])
     *
     * @param  string|string[]  $relations
     * @return static
     */
    public function includeExists(string|array $relations): static
    {
        return $this->queueAggregation('exists', $relations);
    }

    /**
     * Queue a SUM aggregation on one or more relations.
     *
     * Usage:
     *   ->includeSum('orders:total')
     *   ->includeSum(['orders:total', 'orders:tax'])
     *
     * @param  string|string[]  $relations  Format: 'relation:column'
     * @return static
     */
    public function includeSum(string|array $relations): static
    {
        return $this->queueAggregation('sum', $relations);
    }

    /**
     * Queue an AVG aggregation on one or more relations.
     *
     * Usage:
     *   ->includeAvg('reviews:rating')
     *   ->includeAvg(['reviews:rating', 'reviews:score'])
     *
     * @param  string|string[]  $relations  Format: 'relation:column'
     * @return static
     */
    public function includeAvg(string|array $relations): static
    {
        return $this->queueAggregation('avg', $relations);
    }

    /**
     * Queue a MIN aggregation on one or more relations.
     *
     * Usage:
     *   ->includeMin('orders:total')
     *
     * @param  string|string[]  $relations  Format: 'relation:column'
     * @return static
     */
    public function includeMin(string|array $relations): static
    {
        return $this->queueAggregation('min', $relations);
    }

    /**
     * Queue a MAX aggregation on one or more relations.
     *
     * Usage:
     *   ->includeMax('orders:total')
     *
     * @param  string|string[]  $relations  Format: 'relation:column'
     * @return static
     */
    public function includeMax(string|array $relations): static
    {
        return $this->queueAggregation('max', $relations);
    }

    /**
     * Queue an aggregation for the given relations.
     *
     * Parses 'relation:column' syntax for column-based aggregations (sum, avg, etc.).
     * Column-less aggregations (count, exists) use 'relation' syntax.
     *
     * @param  string           $type       Aggregation type name
     * @param  string|string[]  $relations  Relation(s) with optional :column suffix
     * @return static
     */
    private function queueAggregation(string $type, string|array $relations): static
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }

        $aggregation = AggregationRegistry::resolve($type);

        foreach ($relations as $spec) {
            // Parse 'relation:column' syntax
            $parts    = explode(':', $spec, 2);
            $relation = $parts[0];
            $column   = $parts[1] ?? '';

            // Validate the relation exists
            if (!isset($this->relations[$relation])) {
                throw new \InvalidArgumentException(
                    "Relation [{$relation}] is not defined on [" . static::class . "]. " .
                    "Available relations: [" . implode(', ', array_keys($this->relations)) . "]."
                );
            }

            // Validate the aggregation value
            $aggregation->validate($column);

            $this->pendingAggregations[] = new ResolvedAggregation(
                relation:      $relation,
                aggregation:   $aggregation,
                column:        $column,
                attributeName: $aggregation->attributeName($relation, $column),
            );
        }

        return $this;
    }

    // ─── Include (Eager Loading) ──────────────────────────────────────────────

    /**
     * Queue one or more named relations for eager loading.
     *
     * Supports both flat and nested dot-notation:
     *   ->include('posts')
     *   ->include(['posts', 'roles'])
     *   ->include('posts.comments,roles')   // comma-separated string (API-friendly)
     *   ->include('posts.comments.likes')   // nested dot-notation
     *
     * @param  string|string[]  $relations
     * @return static
     */
    public function include(string|array $relations): static
    {
        // Normalize string input
        if (is_string($relations)) {
            // Detect advanced IQL syntax: contains '('
            if (IncludeQueryEngine::isAdvancedSyntax($relations)) {
                $engine = new IncludeQueryEngine();
                $resolved = $engine->process($relations, $this->relations, static::class);
                $this->pendingResolved = array_merge($this->pendingResolved, $resolved);
                return $this;
            }

            // Simple comma-separated: 'posts,roles'
            $relations = array_map('trim', explode(',', $relations));
        }

        foreach ($relations as $relation) {
            if ($relation !== '' && !in_array($relation, $this->pendingIncludes, true)) {
                $this->pendingIncludes[] = $relation;
            }
        }

        return $this;
    }

    /**
     * Eager-load all queued relations onto a set of hydrated models.
     *
     * Supports nested dot-notation (e.g., 'posts.comments.likes').
     * Delegates to a RelationLoader strategy per relation type.
     * Each loader executes exactly ONE query — no N+1.
     *
     * @param  Model[]  $models
     * @return Model[]
     */
    protected function loadIncludes(array $models): array
    {
        if (empty($models)) {
            return $models;
        }

        // ── Process advanced IQL resolved includes ───────────────────────────
        if (!empty($this->pendingResolved)) {
            $models = $this->loadResolvedIncludes($models, $this->pendingResolved);
            $this->pendingResolved = [];
        }

        // ── Process pending aggregations (from programmatic API) ─────────────
        if (!empty($this->pendingAggregations)) {
            $models = $this->loadAggregations($models, $this->pendingAggregations);
            $this->pendingAggregations = [];
        }

        if (empty($this->pendingIncludes)) {
            return $models;
        }

        // ── Parse dot-notation into a tree ───────────────────────────────────
        // Input:  ['posts.comments.likes', 'posts.author', 'roles']
        // Output: ['posts' => ['comments.likes', 'author'], 'roles' => []]
        $tree = $this->parseIncludeTree($this->pendingIncludes);

        // ── Load each top-level relation ─────────────────────────────────────
        foreach ($tree as $relation => $nested) {
            if (!isset($this->relations[$relation])) {
                throw new \InvalidArgumentException(
                    "Relation [{$relation}] is not defined on [" . static::class . "]. " .
                        "Available relations: [" . implode(', ', array_keys($this->relations)) . "]."
                );
            }

            $config = $this->relations[$relation];
            $type   = $config['type'] ?? throw new \InvalidArgumentException(
                "Relation [{$relation}] is missing the required [type] key."
            );

            // Resolve the correct strategy and execute it
            $loader = RelationRegistry::resolve($type);
            $models = $loader->load($models, $relation, $config);

            // ── Process nested includes recursively ──────────────────────────
            if (!empty($nested)) {
                $models = $this->loadNestedIncludes($models, $relation, $config, $nested);
            }
        }

        // ── Stamp _includes on each model ────────────────────────────────────
        $includeNames = array_keys($tree);
        foreach ($models as $model) {
            $existing           = $model->get('_includes', []);
            $model->_includes   = array_unique(array_merge($existing, $includeNames));
        }

        // Reset for the next query chain
        $this->pendingIncludes = [];

        return $models;
    }

    /**
     * Load IQL resolved includes with constraint closures.
     *
     * @param  Model[]            $models
     * @param  ResolvedInclude[]  $resolvedIncludes
     * @return Model[]
     */
    private function loadResolvedIncludes(array $models, array $resolvedIncludes): array
    {
        $includeNames = [];

        foreach ($resolvedIncludes as $resolved) {
            $relation = $resolved->relation;
            $includeNames[] = $relation;

            if (!isset($this->relations[$relation])) {
                throw new \InvalidArgumentException(
                    "Relation [{$relation}] is not defined on [" . static::class . "]. " .
                        "Available relations: [" . implode(', ', array_keys($this->relations)) . "]."
                );
            }

            $config = $this->relations[$relation];
            $type   = $config['type'] ?? throw new \InvalidArgumentException(
                "Relation [{$relation}] is missing the required [type] key."
            );

            // ── Load aggregations if present ─────────────────────────────────
            if ($resolved->hasAggregations()) {
                $models = $this->loadAggregations($models, $resolved->aggregations);
            }

            // ── Load relation data (skip if aggregation-only) ────────────────
            if (!$resolved->isAggregationOnly()) {
                $loader = RelationRegistry::resolve($type);
                $models = $loader->load($models, $relation, $config, $resolved->constraint);

                // Process nested resolved children
                if ($resolved->hasChildren()) {
                    $models = $this->loadNestedResolvedIncludes(
                        $models, $relation, $config, $resolved->children
                    );
                }
            }
        }

        // Stamp _includes
        foreach ($models as $model) {
            $existing         = $model->get('_includes', []);
            $model->_includes = array_unique(array_merge($existing, $includeNames));
        }

        return $models;
    }

    /**
     * Recursively load nested IQL resolved includes on related models.
     *
     * @param  Model[]            $parentModels
     * @param  string             $relation
     * @param  array              $config
     * @param  ResolvedInclude[]  $children
     * @return Model[]
     */
    private function loadNestedResolvedIncludes(
        array $parentModels,
        string $relation,
        array $config,
        array $children,
    ): array {
        // Collect all related models from the loaded relation
        $relatedModels = [];
        foreach ($parentModels as $model) {
            $value = $model->get($relation);

            if ($value instanceof Model) {
                $relatedModels[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Model) {
                        $relatedModels[] = $item;
                    }
                }
            }
        }

        if (empty($relatedModels)) {
            return $parentModels;
        }

        // Resolve the child Table Gateway
        $childTable = $this->resolveChildTable($config['model']);

        // Use reflection to call loadResolvedIncludes on the child table
        $childTable->pendingResolved = $children;
        $childTable->loadIncludes($relatedModels);

        return $parentModels;
    }

    /**
     * Parse an array of include strings (possibly dot-notated) into a tree.
     *
     * Input:  ['posts.comments.likes', 'posts.author', 'roles']
     * Output: ['posts' => ['comments.likes', 'author'], 'roles' => []]
     *
     * @param  string[]  $includes
     * @return array<string, string[]>
     */
    private function parseIncludeTree(array $includes): array
    {
        $tree = [];

        foreach ($includes as $include) {
            $parts    = explode('.', $include, 2);
            $topLevel = $parts[0];
            $rest     = $parts[1] ?? null;

            if (!isset($tree[$topLevel])) {
                $tree[$topLevel] = [];
            }

            if ($rest !== null) {
                $tree[$topLevel][] = $rest;
            }
        }

        return $tree;
    }

    /**
     * Recursively load nested includes on related models.
     *
     * After loading 'posts' on users, this method collects all loaded post
     * models and uses the PostTable to load 'comments' on them, etc.
     *
     * @param  Model[]   $parentModels  Parent models with top-level relation already loaded
     * @param  string    $relation      The relation name (e.g., 'posts')
     * @param  array     $config        The relation config from the registry
     * @param  string[]  $nested        Remaining nested includes (e.g., ['comments.likes', 'author'])
     * @return Model[]
     */
    private function loadNestedIncludes(array $parentModels, string $relation, array $config, array $nested): array
    {
        // Collect all related models from the loaded relation
        $relatedModels = [];
        foreach ($parentModels as $model) {
            $value = $model->get($relation);

            if ($value instanceof Model) {
                $relatedModels[] = $value;
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof Model) {
                        $relatedModels[] = $item;
                    }
                }
            }
        }

        if (empty($relatedModels)) {
            return $parentModels;
        }

        // Resolve the child Table Gateway for the related model
        $childTable = $this->resolveChildTable($config['model']);
        $childTable->include($nested);
        $childTable->loadIncludes($relatedModels);

        return $parentModels;
    }

    /**
     * Resolve the Table Gateway class for a given Model class.
     *
     * Convention: App\Models\Post → App\Tables\PostTable
     *
     * Can be overridden in a relation config with the optional 'table' key:
     *   'posts' => ['type' => 'hasMany', 'model' => Post::class, 'table' => CustomPostTable::class, ...]
     *
     * @param  string  $modelClass  Fully qualified model class name
     * @return Table
     */
    private function resolveChildTable(string $modelClass, ?string $tableOverride = null): Table
    {
        if ($tableOverride) {
            return new $tableOverride();
        }

        // Convention: App\Models\Post → App\Tables\PostTable
        $baseName   = (new \ReflectionClass($modelClass))->getShortName();
        $tableClass = str_replace('\\Models\\', '\\Tables\\', $modelClass);
        $tableClass = preg_replace('/\\\\' . preg_quote($baseName) . '$/', '\\' . $baseName . 'Table', $tableClass);

        if (!class_exists($tableClass)) {
            throw new \RuntimeException(
                "Cannot resolve Table Gateway for model [{$modelClass}]. " .
                    "Expected class [{$tableClass}] does not exist. " .
                    "Define it or add a 'table' key to the relation config."
            );
        }

        return new $tableClass();
    }

    // ─── Aggregation loading ──────────────────────────────────────────────────

    /**
     * Load aggregation results and inject them into parent models.
     *
     * Groups aggregations by relation for optimal query batching —
     * multiple aggregations on the same relation are combined into
     * a single SQL query.
     *
     * @param  Model[]               $models
     * @param  ResolvedAggregation[] $aggregations
     * @return Model[]
     */
    private function loadAggregations(array $models, array $aggregations): array
    {
        if (empty($models) || empty($aggregations)) {
            return $models;
        }

        // Group aggregations by relation for batch loading
        $grouped = [];
        foreach ($aggregations as $agg) {
            $grouped[$agg->relation][] = $agg;
        }

        $loader = new AggregationLoader();

        foreach ($grouped as $relation => $relationAggs) {
            if (!isset($this->relations[$relation])) {
                throw new \InvalidArgumentException(
                    "Relation [{$relation}] is not defined on [" . static::class . "]. " .
                    "Available relations: [" . implode(', ', array_keys($this->relations)) . "]."
                );
            }

            $config = $this->relations[$relation];
            $models = $loader->load($models, $relation, $config, $relationAggs);
        }

        return $models;
    }

    // ─── Internal helpers ─────────────────────────────────────────────────────

    private function newQuery(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()->from($this->table);
    }

    private function hydrate(array $rows): array
    {
        return array_map(fn($row) => (clone $this->blueprint)->fill($row), $rows);
    }

    // ─── Fetch ────────────────────────────────────────────────────────────────

    public function fetchAll(): array
    {
        $rows = $this->newQuery()
            ->select('*')
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->loadIncludes($this->hydrate($rows));
    }

    public function fetchById(int|string $id): ?Model
    {
        $row = $this->newQuery()
            ->select('*')
            ->where($this->primaryKey . ' = :id')
            ->setParameter('id', $id)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return null;
        }

        $models = $this->loadIncludes([$this->hydrate([$row])[0]]);
        return $models[0];
    }

    public function fetchByIdOrFail(int|string $id): Model
    {
        $model = $this->fetchById($id);
        if (!$model) {
            throw new \RuntimeException(
                get_class($this->blueprint) . " row with {$this->primaryKey} = [{$id}] was not found."
            );
        }
        return $model;
    }

    public function fetchWhere(string $column, mixed $value): array
    {
        $rows = $this->newQuery()
            ->select('*')
            ->where("{$column} = :val")
            ->setParameter('val', $value)
            ->executeQuery()
            ->fetchAllAssociative();

        return $this->loadIncludes($this->hydrate($rows));
    }

    public function fetchFirst(string $column, mixed $value): ?Model
    {
        $row = $this->newQuery()
            ->select('*')
            ->where("{$column} = :val")
            ->setParameter('val', $value)
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (!$row) {
            return null;
        }

        $models = $this->loadIncludes([$this->hydrate([$row])[0]]);
        return $models[0];
    }

    public function count(): int
    {
        return (int) $this->newQuery()
            ->select('COUNT(*)')
            ->executeQuery()
            ->fetchOne();
    }

    public function exists(): bool
    {
        return (bool) $this->newQuery()
            ->select('1')
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Get a raw QueryBuilder for advanced custom queries.
     */
    public function builder(): QueryBuilder
    {
        return $this->newQuery()->select('*');
    }

    /**
     * Run a raw builder and get back hydrated model instances (with includes).
     */
    public function fetchFromBuilder(QueryBuilder $qb): array
    {
        return $this->loadIncludes($this->hydrate($qb->executeQuery()->fetchAllAssociative()));
    }

    /**
     * Paginate results from a raw QueryBuilder instance.
     */
    public function paginateFromBuilder(QueryBuilder $qb, int $perPage = 15, int $page = 1): array
    {
        $paginator     = new Paginator();
        $result        = $paginator->paginate($qb, $perPage, $page);
        $result['data'] = $this->loadIncludes($this->hydrate($result['data']));
        return $result;
    }

    // ─── Persist ──────────────────────────────────────────────────────────────

    /**
     * Insert a plain array and return the hydrated model.
     */
    public function insert(array $data): Model
    {
        $this->connection->insert($this->table, $data);
        $data[$this->primaryKey] = (int) $this->connection->lastInsertId();
        return (clone $this->blueprint)->fill($data);
    }

    /**
     * Persist a Model instance (INSERT or UPDATE based on PK presence).
     */
    public function save(Model $model): Model
    {
        $pk = $this->primaryKey;

        if ($model->hasPrimaryKey()) {
            $id   = $model->getPrimaryKeyValue();
            $data = $model->toArray();
            unset($data[$pk]);
            $this->connection->update($this->table, $data, [$pk => $id]);
        } else {
            $this->connection->insert($this->table, $model->toArray());
            $model->set($pk, (int) $this->connection->lastInsertId());
        }

        return $model;
    }

    /**
     * Update columns for a specific row by its primary key.
     */
    public function update(int|string $id, array $data): int
    {
        return $this->connection->update(
            $this->table,
            $data,
            [$this->primaryKey => $id]
        );
    }

    /**
     * Delete a model instance from the database.
     */
    public function delete(Model $model): bool
    {
        if (!$model->hasPrimaryKey()) {
            return false;
        }
        $this->connection->delete($this->table, [$this->primaryKey => $model->getPrimaryKeyValue()]);
        return true;
    }

    /**
     * Delete a row by primary key directly.
     */
    public function deleteById(int|string $id): int
    {
        return $this->connection->delete($this->table, [$this->primaryKey => $id]);
    }

    // ─── Pagination ───────────────────────────────────────────────────────────

    /**
     * Paginate results with eager loading support.
     *
     * Returns ['data' => Model[], 'meta' => [...]] where data contains
     * hydrated Model instances with all pending includes loaded.
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $paginator = new Paginator();
        $result    = $paginator->paginate($this->builder(), $perPage, $page);

        // Hydrate raw rows into Model instances and load includes
        $models        = $this->loadIncludes($this->hydrate($result['data']));
        $result['data'] = $models;

        return $result;
    }
}
