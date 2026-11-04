<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Http;

use Exception;

class ValidationException extends Exception
{
    /** @var array<string, string[]> */
    public readonly array $errors;

    /** @param array<string, string[]> $errors */
    public function __construct(array $errors)
    {
        parent::__construct('The given data was invalid.', 422);
        $this->errors = $errors;
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
