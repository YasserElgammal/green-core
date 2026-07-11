<?php

namespace YasserElgammal\Green\Database\Query\Traits;

use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Query\GreenQuery;

/**
 * Condition-building methods for GreenQuery.
 *
 * @template TModel of Model
 */
trait BuildsConditions
{
    /**
     * Add an AND condition.
     *
     * Supported forms:
     *   ->where('status', 'active')
     *   ->where('age', '>=', 18)
     *   ->where(['status' => 'active', 'role' => 'admin'])
     *
     * @param string|array<string, mixed> $column
     */
    public function where(string|array $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $item) {
                $this->addBasicCondition('AND', $key, '=', $item);
            }

            return $this;
        }

        [$operator, $conditionValue] = func_num_args() === 2
            ? ['=', $operatorOrValue]
            : [$operatorOrValue, $value];

        $this->addBasicCondition('AND', $column, (string) $operator, $conditionValue);

        return $this;
    }

    /**
     * Add an OR condition.
     *
     * @param string|array<string, mixed> $column
     */
    public function orWhere(string|array $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $item) {
                $this->addBasicCondition('OR', $key, '=', $item);
            }

            return $this;
        }

        [$operator, $conditionValue] = func_num_args() === 2
            ? ['=', $operatorOrValue]
            : [$operatorOrValue, $value];

        $this->addBasicCondition('OR', $column, (string) $operator, $conditionValue);

        return $this;
    }

    public function whereGroup(callable $callback): static
    {
        return $this->addGroupCondition('AND', $callback);
    }

    public function orWhereGroup(callable $callback): static
    {
        return $this->addGroupCondition('OR', $callback);
    }

    private function addGroupCondition(string $boolean, callable $callback): static
    {
        $group = new GreenQuery($this->table, $this->builder);
        $group->parameterIndex = $this->parameterIndex;

        $callback($group);

        $this->parameterIndex = $group->parameterIndex;
        $sql = $group->compileConditions();

        if ($sql !== '') {
            $this->conditions[] = ['boolean' => $boolean, 'sql' => "({$sql})"];
        }

        return $this;
    }

    /** @param array<int, mixed> $values */
    public function whereIn(string $column, array $values): static
    {
        return $this->addListCondition('AND', $column, $values, false);
    }

    /** @param array<int, mixed> $values */
    public function orWhereIn(string $column, array $values): static
    {
        return $this->addListCondition('OR', $column, $values, false);
    }

    /** @param array<int, mixed> $values */
    public function whereNotIn(string $column, array $values): static
    {
        return $this->addListCondition('AND', $column, $values, true);
    }

    /** @param array<int, mixed> $values */
    public function orWhereNotIn(string $column, array $values): static
    {
        return $this->addListCondition('OR', $column, $values, true);
    }

    /** @param array<int, mixed> $values */
    private function addListCondition(string $boolean, string $column, array $values, bool $not): static
    {
        $this->assertColumn($column);

        if ($values === []) {
            $this->conditions[] = [
                'boolean' => $boolean,
                'sql'     => $not ? '1 = 1' : '1 = 0',
            ];
            return $this;
        }

        $parameters = [];
        foreach ($values as $item) {
            $parameter = $this->nextParameter();
            $this->builder->setParameter($parameter, $item);
            $parameters[] = ":{$parameter}";
        }

        $this->conditions[] = [
            'boolean' => $boolean,
            'sql'     => "{$column} " . ($not ? 'NOT IN' : 'IN') . " (" . implode(', ', $parameters) . ")",
        ];

        return $this;
    }

    public function whereNull(string $column): static
    {
        return $this->addNullCondition('AND', $column, false);
    }

    public function orWhereNull(string $column): static
    {
        return $this->addNullCondition('OR', $column, false);
    }

    public function whereNotNull(string $column): static
    {
        return $this->addNullCondition('AND', $column, true);
    }

    public function orWhereNotNull(string $column): static
    {
        return $this->addNullCondition('OR', $column, true);
    }

    private function addNullCondition(string $boolean, string $column, bool $not): static
    {
        $this->assertColumn($column);
        $this->conditions[] = [
            'boolean' => $boolean,
            'sql'     => "{$column} IS " . ($not ? 'NOT NULL' : 'NULL'),
        ];

        return $this;
    }

    public function whereBetween(string $column, mixed $from, mixed $to): static
    {
        return $this->addBetweenCondition('AND', $column, $from, $to, false);
    }

    public function orWhereBetween(string $column, mixed $from, mixed $to): static
    {
        return $this->addBetweenCondition('OR', $column, $from, $to, false);
    }

    public function whereNotBetween(string $column, mixed $from, mixed $to): static
    {
        return $this->addBetweenCondition('AND', $column, $from, $to, true);
    }

    public function orWhereNotBetween(string $column, mixed $from, mixed $to): static
    {
        return $this->addBetweenCondition('OR', $column, $from, $to, true);
    }

    private function addBetweenCondition(string $boolean, string $column, mixed $from, mixed $to, bool $not): static
    {
        $this->assertColumn($column);

        $fromParameter = $this->nextParameter();
        $toParameter   = $this->nextParameter();

        $this->builder
            ->setParameter($fromParameter, $from)
            ->setParameter($toParameter, $to);

        $this->conditions[] = [
            'boolean' => $boolean,
            'sql'     => "{$column} " . ($not ? 'NOT BETWEEN' : 'BETWEEN') . " :{$fromParameter} AND :{$toParameter}",
        ];

        return $this;
    }

    public function whereLike(string $column, string $pattern): static
    {
        return $this->where($column, 'LIKE', $pattern);
    }

    public function orWhereLike(string $column, string $pattern): static
    {
        return $this->orWhere($column, 'LIKE', $pattern);
    }

    public function whereNotLike(string $column, string $pattern): static
    {
        return $this->where($column, 'NOT LIKE', $pattern);
    }

    public function orWhereNotLike(string $column, string $pattern): static
    {
        return $this->orWhere($column, 'NOT LIKE', $pattern);
    }
}
