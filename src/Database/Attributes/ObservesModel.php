<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database\Attributes;

use Attribute;

/**
 * Declare which Model class an Observer watches.
 *
 * Usage:
 *   #[ObservesModel(User::class)]
 *   class UserObserver extends Observer { ... }
 *
 * The attribute is read by ObserverRegistry during registration
 * to automatically bind the observer to the correct model class,
 * removing the need for a separate mapping step.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class ObservesModel
{
    /**
     * @param class-string<\YasserElgammal\Green\Database\Model> $model
     */
    public function __construct(
        public string $model,
    ) {
    }
}
