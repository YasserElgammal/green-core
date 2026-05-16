<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Exceptions;

/**
 * Thrown when an unregistered operation name is used in an include query.
 *
 * Example: include('posts(foo:bar)') where 'foo' is not a registered operation.
 */
class UnknownOperationException extends IncludeQueryException
{
    public function __construct(
        private readonly string $operationName,
        private readonly string $relation,
        array $availableOperations = [],
    ) {
        $available = !empty($availableOperations)
            ? ' Available operations: [' . implode(', ', $availableOperations) . '].'
            : '';

        parent::__construct(
            "Unknown operation [{$operationName}] on relation [{$relation}].{$available}"
        );
    }

    public function getOperationName(): string
    {
        return $this->operationName;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }
}
