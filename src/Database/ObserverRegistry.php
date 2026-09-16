<?php

declare(strict_types=1);

namespace YasserElgammal\Green\Database;

use Closure;
use ReflectionClass;
use YasserElgammal\Green\Database\Attributes\ObservesModel;

/**
 * Manages the mapping of Model classes to their Observer instances.
 *
 * Observers can be registered in three ways:
 *
 *   1. Attribute-based: pass the observer class, and the registry reads
 *      #[ObservesModel(Model::class)] attributes to auto-bind it.
 *
 *   2. Explicit: pass both the model class and the observer class/instance.
 *
 *   3. Declarative: Table subclasses list observer classes in their
 *      $observers property, which Table::bootObservers() registers here.
 *
 * The registry is a singleton owned by the application container.
 */
final class ObserverRegistry
{
    /**
     * @var array<class-string<Model>, Observer[]>
     */
    private array $observers = [];

    private readonly ?Closure $resolver;

    /**
     * @param callable(class-string): Observer|null $resolver
     *        Container resolver for instantiating observer classes.
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver === null ? null : Closure::fromCallable($resolver);
    }

    /**
     * Register an observer for a specific model class.
     *
     * @param class-string<Model> $modelClass
     * @param class-string<Observer>|Observer $observer
     */
    public function register(string $modelClass, string|Observer $observer): void
    {
        $instance = $this->resolveObserver($observer);
        $this->observers[$modelClass][] = $instance;
    }

    /**
     * Register an observer using its #[ObservesModel] attribute(s).
     *
     * If the observer class has one or more #[ObservesModel(Model::class)]
     * attributes, it will be registered for each declared model automatically.
     *
     * @param class-string<Observer>|Observer $observer
     * @return string[] The model classes the observer was registered for.
     *
     * @throws \InvalidArgumentException If the class has no #[ObservesModel] attributes.
     */
    public function registerFromAttribute(string|Observer $observer): array
    {
        $instance = $this->resolveObserver($observer);
        $reflection = new ReflectionClass($instance);
        $attributes = $reflection->getAttributes(ObservesModel::class);

        if (empty($attributes)) {
            throw new \InvalidArgumentException(sprintf(
                'Observer [%s] has no #[ObservesModel] attribute. '
                . 'Use register($modelClass, $observer) for explicit registration.',
                $instance::class,
            ));
        }

        $registered = [];

        foreach ($attributes as $attribute) {
            /** @var ObservesModel $observesModel */
            $observesModel = $attribute->newInstance();
            $this->observers[$observesModel->model][] = $instance;
            $registered[] = $observesModel->model;
        }

        return $registered;
    }

    /**
     * Get all observers registered for a given model class.
     *
     * @param class-string<Model> $modelClass
     * @return Observer[]
     */
    public function getObservers(string $modelClass): array
    {
        return $this->observers[$modelClass] ?? [];
    }

    /**
     * Check if any observers are registered for a given model class.
     *
     * @param class-string<Model> $modelClass
     */
    public function hasObservers(string $modelClass): bool
    {
        return !empty($this->observers[$modelClass]);
    }

    /**
     * Remove all observers for a given model class.
     *
     * @param class-string<Model> $modelClass
     */
    public function flush(string $modelClass): void
    {
        unset($this->observers[$modelClass]);
    }

    /**
     * Remove all registered observers.
     */
    public function flushAll(): void
    {
        $this->observers = [];
    }

    /**
     * Resolve an observer class string into an instance.
     */
    private function resolveObserver(string|Observer $observer): Observer
    {
        if ($observer instanceof Observer) {
            return $observer;
        }

        if ($this->resolver !== null) {
            $resolved = ($this->resolver)($observer);

            if (!$resolved instanceof Observer) {
                throw new \RuntimeException(sprintf(
                    'Resolved observer [%s] must be an instance of [%s].',
                    $observer,
                    Observer::class,
                ));
            }

            return $resolved;
        }

        if (!class_exists($observer)) {
            throw new \RuntimeException(sprintf(
                'Observer class [%s] does not exist.',
                $observer,
            ));
        }

        return new $observer();
    }
}
