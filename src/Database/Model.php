<?php

namespace YasserElgammal\Green\Database;

/**
 * Model is a pure data container (Data Transfer Object).
 * It holds attributes but has NO database logic itself.
 * All DB operations go through Table.
 *
 * Implements \JsonSerializable so nested models serialize
 * correctly when passed to json_encode() or response_json().
 */
abstract class Model implements \JsonSerializable
{
    protected string $table      = '';
    protected string $primaryKey = 'id';

    private array $attributes = [];

    private static array $attributeMethods = [];

    private array $loadedRelations = [];

    /** Snapshot of attributes at hydration time — used for dirty tracking */
    private array $original = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ─── Attribute access ─────────────────────────────────────────────────────

    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    public function set(string $key, mixed $value): static
    {
        $method = isset($this->loadedRelations[$key]) ? null : $this->attributeMethod('set', $key);
        $this->attributes[$key] = $method === null ? $value : $this->$method($value);
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            return $default;
        }
        $method = isset($this->loadedRelations[$key]) ? null : $this->attributeMethod('get', $key);
        return $method === null ? ($this->attributes[$key] ?? $default) : $this->$method($this->attributes[$key]);
    }

    /** Read stored state for persistence and relation matching, bypassing getters. */
    public function getRaw(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /** Merge persisted values without application-side setters. */
    public function fillRaw(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    /** Attach an already loaded relation, including null and empty collections. */
    public function setRelation(string $key, mixed $value): static
    {
        $this->loadedRelations[$key] = true;
        $this->attributes[$key] = $value;
        return $this;
    }

    /** Resolve only protected, non-static, single-value hooks outside Model. */
    private function attributeMethod(string $prefix, string $key): ?string
    {
        if (str_starts_with($key, '_')) {
            return null;
        }
        $class = static::class;
        $cacheKey = $prefix . ':' . $key;
        if (!array_key_exists($cacheKey, self::$attributeMethods[$class] ?? [])) {
            $name = $prefix . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
            $method = method_exists($class, $name) ? new \ReflectionMethod($class, $name) : null;
            self::$attributeMethods[$class][$cacheKey] = $method !== null
                && $method->getDeclaringClass()->getName() !== self::class
                && $method->isProtected() && !$method->isStatic()
                && $method->getNumberOfParameters() === 1
                ? $method->getName() : null;
        }
        return self::$attributeMethods[$class][$cacheKey];
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    // ─── Dirty Tracking ───────────────────────────────────────────────────────

    /**
     * Sync the original attributes with the current state.
     * Called after hydration and after persistence to reset dirty state.
     */
    public function syncOriginal(): static
    {
        $this->original = $this->attributes;
        return $this;
    }

    /**
     * Get only the attributes that have changed since the last sync.
     */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (str_starts_with($key, '_')) {
                continue; // Skip meta keys
            }
            if (!array_key_exists($key, $this->original) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    /**
     * Check if the model (or a specific attribute) has been modified.
     */
    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            return ($this->attributes[$key] ?? null) !== ($this->original[$key] ?? null);
        }
        return !empty($this->getDirty());
    }

    /**
     * Check if the model (or a specific attribute) is unchanged.
     */
    public function isClean(?string $key = null): bool
    {
        return !$this->isDirty($key);
    }

    /**
     * Get the original value of an attribute before modification.
     */
    public function getOriginal(string $key, mixed $default = null): mixed
    {
        return $this->original[$key] ?? $default;
    }

    // ─── Serialization ────────────────────────────────────────────────────────

    /**
     * Convert this model (and any attached relations) into a plain array.
     *
     * Relations are stored as Model instances or arrays of Model instances
     * inside $attributes. This method recursively resolves them so the
     * output is always a plain PHP array — safe for json_encode().
     */
    public function toArray(): array
    {
        $attributes = $this->attributes;
        foreach ($attributes as $key => $value) {
            if (!str_starts_with($key, '_')) {
                $attributes[$key] = $this->get($key);
            }
        }
        return $this->resolveAttributes($attributes);
    }

    /** Export stored values without getters, excluding internal metadata. */
    public function toRawArray(): array
    {
        return $this->resolveAttributes($this->attributes, false);
    }

    /** Serialize the same getter-aware output as toArray(). */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // ─── Meta ─────────────────────────────────────────────────────────────────

    public function getTable(): string
    {
        return $this->table;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function hasPrimaryKey(): bool
    {
        return isset($this->attributes[$this->primaryKey]);
    }

    public function getPrimaryKeyValue(): int|string|null
    {
        return $this->attributes[$this->primaryKey] ?? null;
    }

    // ─── Internal ─────────────────────────────────────────────────────────────

    /**
     * Recursively resolve attributes, turning nested Model instances
     * and arrays of Model instances into plain arrays.
     */
    private function resolveAttributes(array $attributes, bool $getters = true): array
    {
        $resolved = [];

        foreach ($attributes as $key => $value) {
            // Skip internal meta keys (e.g., _includes)
            if (str_starts_with($key, '_')) {
                continue;
            }

            $resolved[$key] = match (true) {
                // Single nested model
                $value instanceof self  => $getters ? $value->toArray() : $value->toRawArray(),

                // Array — may contain Model instances or scalars
                is_array($value)        => array_map(
                    fn($item) => $item instanceof self ? ($getters ? $item->toArray() : $item->toRawArray()) : $item,
                    $value
                ),

                // Scalar / null / other
                default                 => $value,
            };
        }

        return $resolved;
    }
}
