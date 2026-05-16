<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Exceptions;

/**
 * Thrown when a relation name in the include query does not exist
 * in the Table's relation registry.
 */
class UnknownRelationException extends IncludeQueryException
{
    public function __construct(
        private readonly string $relationName,
        private readonly string $tableClass,
        array $availableRelations = [],
    ) {
        $available = !empty($availableRelations)
            ? ' Available relations: [' . implode(', ', $availableRelations) . '].'
            : '';

        parent::__construct(
            "Relation [{$relationName}] is not defined on [{$tableClass}].{$available}"
        );
    }

    public function getRelationName(): string
    {
        return $this->relationName;
    }

    public function getTableClass(): string
    {
        return $this->tableClass;
    }
}
