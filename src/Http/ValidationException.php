<?php

namespace YasserElgammal\Green\Http;

use Exception;

class ValidationException extends Exception
{
    protected array $errors;

    public function __construct(array $errors)
    {
        parent::__construct('The given data was invalid.', 422);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
