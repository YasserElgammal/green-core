<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Exceptions;

/**
 * Thrown when an operation value fails validation.
 *
 * Example: include('posts(limit:abc)') — 'abc' is not a valid integer.
 */
class InvalidOperationValueException extends IncludeQueryException
{
    public function __construct(
        private readonly string $operationName,
        private readonly string $rawValue,
        string $reason = '',
    ) {
        $hint = $reason !== '' ? " {$reason}" : '';

        parent::__construct(
            "Invalid value [{$rawValue}] for operation [{$operationName}].{$hint}"
        );
    }

    public function getOperationName(): string
    {
        return $this->operationName;
    }

    public function getRawValue(): string
    {
        return $this->rawValue;
    }
}
