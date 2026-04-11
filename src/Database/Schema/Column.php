<?php

namespace YasserElgammal\Green\Database\Schema;

/**
 * Represents a single table column with a fluent builder API.
 * Tracks type, constraints, and modifiers for SQL generation.
 */
class Column
{
    public string  $name;
    public string  $type;
    public bool    $isNullable  = false;
    public bool    $isUnique    = false;
    public mixed   $defaultVal  = null;
    public bool    $hasDefault  = false;
    public bool    $autoIncrement = false;
    public ?int    $length      = null;

    public function __construct(string $name, string $type, ?int $length = null)
    {
        $this->name   = $name;
        $this->type   = $type;
        $this->length = $length;
    }

    // ─── Static Factories ──────────────────────────────────────────────────

    public static function string(string $name, int $length = 255): static
    {
        return new static($name, 'VARCHAR', $length);
    }

    public static function integer(string $name): static
    {
        return new static($name, 'INT');
    }

    public static function bigInteger(string $name): static
    {
        return new static($name, 'BIGINT');
    }

    public static function text(string $name): static
    {
        return new static($name, 'TEXT');
    }

    public static function boolean(string $name): static
    {
        return new static($name, 'TINYINT', 1);
    }

    public static function uuid(string $name): static
    {
        return new static($name, 'CHAR', 36);
    }

    public static function timestamp(string $name): static
    {
        return new static($name, 'TIMESTAMP');
    }

    public static function date(string $name): static
    {
        return new static($name, 'DATE');
    }

    public static function decimal(string $name, int $precision = 8, int $scale = 2): static
    {
        $col = new static($name, 'DECIMAL');
        $col->length = null;               // precision/scale override length
        $col->type = "DECIMAL({$precision},{$scale})";
        return $col;
    }

    public static function json(string $name): static
    {
        return new static($name, 'JSON');
    }

    // ─── Modifiers ─────────────────────────────────────────────────────────

    public function nullable(): static
    {
        $this->isNullable = true;
        return $this;
    }

    public function unique(): static
    {
        $this->isUnique = true;
        return $this;
    }

    public function default(mixed $value): static
    {
        $this->defaultVal = $value;
        $this->hasDefault = true;
        return $this;
    }

    public function autoIncrement(): static
    {
        $this->autoIncrement = true;
        return $this;
    }

    // ─── SQL Generation ────────────────────────────────────────────────────

    /**
     * Render the column definition for CREATE TABLE / ALTER TABLE ADD.
     */
    public function toSql(): string
    {
        $type = $this->typeString();

        $sql = "`{$this->name}` {$type}";

        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        $sql .= $this->isNullable ? ' NULL' : ' NOT NULL';

        if ($this->hasDefault) {
            $sql .= ' DEFAULT ' . $this->formatDefault($this->defaultVal);
        }

        if ($this->isUnique) {
            $sql .= ' UNIQUE';
        }

        return $sql;
    }

    private function typeString(): string
    {
        // DECIMAL already contains the full type string (e.g. "DECIMAL(8,2)")
        if (str_starts_with($this->type, 'DECIMAL(')) {
            return $this->type;
        }

        return $this->length !== null
            ? "{$this->type}({$this->length})"
            : $this->type;
    }

    private function formatDefault(mixed $value): string
    {
        if (is_null($value))    return 'NULL';
        if (is_bool($value))    return $value ? '1' : '0';
        if (is_numeric($value)) return (string) $value;
        return "'" . addslashes((string) $value) . "'";
    }

    /**
     * Return a snapshot array used by ensure() to compare columns.
     */
    public function toDefinitionArray(): array
    {
        return [
            'type'          => $this->typeString(),
            'nullable'      => $this->isNullable,
            'default'       => $this->hasDefault ? $this->defaultVal : '__none__',
            'autoIncrement' => $this->autoIncrement,
        ];
    }
}
