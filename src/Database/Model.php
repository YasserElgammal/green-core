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

    /** Snapshot of attributes at hydration time — used for dirty tracking */
    private array $original = [];

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ─── Attribute access ─────────────────────────────────────────────────────

    public function fill(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    public function set(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
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
        return $this->resolveAttributes($this->attributes);
    }

    /**
     * Implement JsonSerializable so json_encode($model) just works,
     * including deeply nested relations.
     */
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
    private function resolveAttributes(array $attributes): array
    {
        $resolved = [];

        foreach ($attributes as $key => $value) {
            // Skip internal meta keys (e.g., _includes)
            if (str_starts_with($key, '_')) {
                continue;
            }

            $resolved[$key] = match (true) {
                // Single nested model
                $value instanceof self  => $value->toArray(),

                // Array — may contain Model instances or scalars
                is_array($value)        => array_map(
                    fn($item) => $item instanceof self ? $item->toArray() : $item,
                    $value
                ),

                // Scalar / null / other
                default                 => $value,
            };
        }

        return $resolved;
    }
}
