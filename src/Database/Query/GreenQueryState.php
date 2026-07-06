<?php

namespace YasserElgammal\Green\Database\Query;

use Doctrine\DBAL\Query\QueryBuilder;
use YasserElgammal\Green\Database\Model;
use YasserElgammal\Green\Database\Table;

/**
 * Shared state and SQL compilation for GreenQuery.
 *
 * @template TModel of Model
 */
abstract class GreenQueryState
{
    /** @var array<int, array{boolean: string, sql: string}> */
    protected array $conditions = [];

    protected int $parameterIndex = 0;

    /**
     * @param Table<TModel> $table
     */
    public function __construct(
        protected readonly Table $table,
        protected readonly QueryBuilder $builder,
    ) {
    }

    protected function addBasicCondition(string $boolean, string $column, string $operator, mixed $value): void
    {
        $this->assertColumn($column);
        $operator = strtoupper(trim($operator));

        if (!in_array($operator, ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE'], true)) {
            throw new \InvalidArgumentException("Unsupported where operator [{$operator}].");
        }

        $parameter = $this->nextParameter();
        $this->builder->setParameter($parameter, $value);

        $this->conditions[] = [
            'boolean' => $boolean,
            'sql'     => "{$column} {$operator} :{$parameter}",
        ];
    }

    protected function preparedBuilder(): QueryBuilder
    {
        $builder = clone $this->builder;
        $sql = $this->compileConditions();

        if ($sql !== '') {
            $builder->where($sql);
        }

        return $builder;
    }

    protected function compileConditions(): string
    {
        $sql = '';

        foreach ($this->conditions as $index => $condition) {
            if ($index === 0) {
                $sql = $condition['sql'];
                continue;
            }

            $sql .= ' ' . $condition['boolean'] . ' ' . $condition['sql'];
        }

        return $sql;
    }

    protected function nextParameter(): string
    {
        return 'green_query_' . (++$this->parameterIndex);
    }

    protected function checkedColumn(string $column): string
    {
        $this->assertColumn($column);

        return $column;
    }

    protected function assertColumn(string $column): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name [{$column}].");
        }
    }
}