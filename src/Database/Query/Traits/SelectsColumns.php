<?php

namespace YasserElgammal\Green\Database\Query\Traits;

use YasserElgammal\Green\Database\Model;

/**
 * Column selection methods for GreenQuery.
 *
 * @template TModel of Model
 */
trait SelectsColumns
{
    /**
     * Specify which columns to select for this query.
     *
     * The primary key is automatically included by preparedBuilder()
     * to ensure hydration, dirty-tracking, and relation loading work correctly.
     *
     * Usage:
     *   ->select('id', 'name', 'email')      // variadic strings
     *   ->select(['id', 'name', 'email'])     // single array
     *
     * @param  string|string[]  ...$columns
     * @return static
     */
    public function select(string|array ...$columns): static
    {
        // Normalize: flatten if a single array was passed
        $flat = [];
        foreach ($columns as $col) {
            if (is_array($col)) {
                foreach ($col as $c) {
                    $flat[] = $c;
                }
            } else {
                $flat[] = $col;
            }
        }

        // Validate each column name
        foreach ($flat as $column) {
            $this->assertColumn($column);
        }

        $this->selectedColumns = $flat;

        return $this;
    }
}
