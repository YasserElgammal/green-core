<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Ast;

/**
 * A single parsed operation from the parenthesized block.
 *
 * Immutable value object.
 *
 * Example: `limit:5` → Operation('limit', '5')
 *          `select:id,name` → Operation('select', 'id|name')
 */
final readonly class Operation
{
    public function __construct(
        public string $name,
        public string $rawValue,
    ) {
    }

    /**
     * Return the raw value split by pipe delimiter.
     *
     * Used by multi-value operations like `select:id,name`
     * (stored internally as `id|name` to avoid comma ambiguity).
     *
     * @return string[]
     */
    public function values(): array
    {
        return explode('|', $this->rawValue);
    }
}
