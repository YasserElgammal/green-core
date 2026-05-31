<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Ast;

use YasserElgammal\Green\Database\IncludeQuery\Aggregations\AggregationRegistry;

/**
 * Typed collection of Operation instances parsed from a relation's constraint block.
 *
 * Immutable after construction.
 *
 * Example: `(limit:5,order:desc,select:id,name)` → OperationBag with 3 operations.
 */
final readonly class OperationBag
{
    /** @var array<string, Operation> Keyed by operation name */
    private array $operations;

    /**
     * @param Operation[] $operations
     */
    public function __construct(array $operations = [])
    {
        $indexed = [];
        foreach ($operations as $operation) {
            $indexed[$operation->name] = $operation;
        }
        $this->operations = $indexed;
    }

    public function has(string $name): bool
    {
        return isset($this->operations[$name]);
    }

    public function get(string $name): ?Operation
    {
        return $this->operations[$name] ?? null;
    }

    /**
     * @return Operation[]
     */
    public function all(): array
    {
        return array_values($this->operations);
    }

    public function isEmpty(): bool
    {
        return empty($this->operations);
    }

    public function count(): int
    {
        return count($this->operations);
    }

    /**
     * Return only operations that are registered aggregations.
     *
     * @return Operation[]
     */
    public function getAggregations(): array
    {
        $registry = AggregationRegistry::class;

        return array_values(array_filter(
            $this->operations,
            fn(Operation $op) => $registry::has($op->name),
        ));
    }

    /**
     * Return only operations that are NOT aggregations (regular constraints).
     *
     * @return Operation[]
     */
    public function getNonAggregations(): array
    {
        $registry = AggregationRegistry::class;

        return array_values(array_filter(
            $this->operations,
            fn(Operation $op) => !$registry::has($op->name),
        ));
    }

    /**
     * Whether any operations in this bag are aggregations.
     */
    public function hasAggregations(): bool
    {
        return !empty($this->getAggregations());
    }

    /**
     * @return array<string, string> name → rawValue pairs
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->operations as $name => $operation) {
            $result[$name] = $operation->rawValue;
        }
        return $result;
    }
}
