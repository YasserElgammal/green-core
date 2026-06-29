<?php

namespace YasserElgammal\Green\Auth;

use RuntimeException;

class ForbiddenException extends RuntimeException
{
    public function __construct(
        public readonly string $ability,
        public readonly string $subjectType,
        string $message = 'This action is unauthorized.'
    ) {
        parent::__construct($message, 403);
    }
}
