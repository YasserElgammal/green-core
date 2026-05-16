<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Ast;

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
