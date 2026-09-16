<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database;

/**
 * Base class for model lifecycle observers.
 *
 * Observers react to persistence events on a Table Gateway.
 * "Before" hooks (creating, updating, deleting, saving) return bool:
 *   - true  → continue the operation
 *   - false → halt the operation silently
 *
 * "After" hooks (created, updated, deleted, saved) are fire-and-forget.
 *
 * Register an observer on a Table subclass:
 *
 *   // Declarative (property):
 *   protected array $observers = [UserObserver::class];
 *
 *   // Attribute-based (on the Observer class):
 *   #[ObservesModel(User::class)]
 *   class UserObserver extends Observer { ... }
 *
 *   // Programmatic:
 *   $table->observe(UserObserver::class);
 *
 * Observers are resolved through the application container when
 * registered as class strings, supporting constructor injection.
 */
abstract class Observer
{
    /**
     * Called before a new model is inserted.
     *
     * @return bool Return false to halt the insert.
     */
    public function creating(Model $model): bool
    {
        return true;
    }

    /**
     * Called after a new model has been inserted.
     */
    public function created(Model $model): void
    {
    }

    /**
     * Called before an existing model is updated.
     *
     * @return bool Return false to halt the update.
     */
    public function updating(Model $model): bool
    {
        return true;
    }

    /**
     * Called after an existing model has been updated.
     */
    public function updated(Model $model): void
    {
    }

    /**
     * Called before a model is deleted.
     *
     * @return bool Return false to halt the deletion.
     */
    public function deleting(Model $model): bool
    {
        return true;
    }

    /**
     * Called after a model has been deleted.
     */
    public function deleted(Model $model): void
    {
    }

    /**
     * Called before any persist operation (insert or update).
     *
     * @return bool Return false to halt the operation.
     */
    public function saving(Model $model): bool
    {
        return true;
    }

    /**
     * Called after any persist operation (insert or update).
     */
    public function saved(Model $model): void
    {
    }
}
