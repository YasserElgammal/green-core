<?php

namespace YasserElgammal\Green\Database\Query\Traits;

use YasserElgammal\Green\Database\Model;

/**
 * Aggregation methods for GreenQuery.
 *
 * @template TModel of Model
 */
trait RunsAggregates
{
    public function count(): int
    {
        return (int) $this->aggregate('COUNT(*)');
    }

    public function exists(): bool
    {
        $builder = $this->preparedBuilder();
        $builder->select('1')->setMaxResults(1);

        return (bool) $builder->executeQuery()->fetchOne();
    }

    public function sum(string $column): int|float
    {
        return $this->normalizeNumber($this->aggregate('SUM(' . $this->checkedColumn($column) . ')')) ?? 0;
    }

    public function avg(string $column): int|float|null
    {
        return $this->normalizeNumber($this->aggregate('AVG(' . $this->checkedColumn($column) . ')'));
    }

    public function min(string $column): mixed
    {
        return $this->aggregate('MIN(' . $this->checkedColumn($column) . ')');
    }

    public function max(string $column): mixed
    {
        return $this->aggregate('MAX(' . $this->checkedColumn($column) . ')');
    }

    private function aggregate(string $expression): mixed
    {
        $builder = $this->preparedBuilder();
        $builder->select($expression);
        $builder->setFirstResult(0);
        $builder->setMaxResults(null);

        return $builder->executeQuery()->fetchOne();
    }

    private function normalizeNumber(mixed $value): int|float|null
    {
        if ($value === null || $value === false) {
            return null;
        }

        return ((string) (int) $value === (string) $value)
            ? (int) $value
            : (float) $value;
    }
}