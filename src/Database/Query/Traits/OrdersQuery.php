<?php

namespace YasserElgammal\Green\Database\Query\Traits;

use YasserElgammal\Green\Database\Model;

/**
 * Sorting and windowing methods for GreenQuery.
 *
 * @template TModel of Model
 */
trait OrdersQuery
{
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->assertColumn($column);
        $direction = strtoupper($direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException('Order direction must be [asc] or [desc].');
        }

        $this->builder->orderBy($column, $direction);

        return $this;
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $limit): static
    {
        $this->builder->setMaxResults($limit);

        return $this;
    }

    public function offset(int $offset): static
    {
        $this->builder->setFirstResult($offset);

        return $this;
    }
}