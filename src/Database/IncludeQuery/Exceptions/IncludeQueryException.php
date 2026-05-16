<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\IncludeQuery\Exceptions;

/**
 * Base exception for all Include Query Language errors.
 *
 * Catch this single type to handle any IQL failure generically,
 * or catch a specific subclass for fine-grained control.
 */
class IncludeQueryException extends \RuntimeException
{
}
