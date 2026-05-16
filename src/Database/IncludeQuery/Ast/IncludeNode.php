<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Ast;

/**
 * AST node representing a single relation in the include tree.
 *
 * Forms a recursive linked list for nested relations:
 *   `comments(limit:5).author(select:id,name)`
 *   → IncludeNode('comments', ops) → IncludeNode('author', ops) → null
 *
 * Immutable after construction.
 */
final readonly class IncludeNode
{
    public function __construct(
        public string       $relation,
        public OperationBag $operations,
        public ?IncludeNode $child = null,
    ) {
    }

    /**
     * Whether this node has operations (constraints).
     */
    public function hasOperations(): bool
    {
        return !$this->operations->isEmpty();
    }

    /**
     * Whether this node has a nested child relation.
     */
    public function hasChild(): bool
    {
        return $this->child !== null;
    }

    /**
     * Collect all relation names in the chain (depth-first).
     *
     * @return string[]
     */
    public function flattenRelations(): array
    {
        $names = [$this->relation];

        if ($this->child !== null) {
            $names = array_merge($names, $this->child->flattenRelations());
        }

        return $names;
    }

    /**
     * Debug-friendly array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'relation'   => $this->relation,
            'operations' => $this->operations->toArray(),
        ];

        if ($this->child !== null) {
            $result['child'] = $this->child->toArray();
        }

        return $result;
    }
}
