<?php

namespace YasserElgammal\Green\Database\Query\Traits;

use YasserElgammal\Green\Database\Model;

/**
 * Result-fetching methods for GreenQuery.
 *
 * @template TModel of Model
 */
trait FetchesResults
{
    /** @return array<int, TModel> */
    public function fetch(): array
    {
        return $this->table->fetchFromBuilder($this->preparedBuilder());
    }

    /**
     * Paginate the current query and hydrate the result rows.
     *
     * @return array{data: array<int, TModel>, meta: array<string, mixed>}
     */
    public function paginate(int $perPage = 15, int $page = 1, bool $withCount = true): array
    {
        return $this->table->paginateFromBuilder($this->preparedBuilder(), $perPage, $page, $withCount);
    }

    /** @return TModel|null */
    public function first(): ?Model
    {
        $builder = $this->preparedBuilder();
        $builder->setMaxResults(1);

        $models = $this->table->fetchFromBuilder($builder);

        return $models[0] ?? null;
    }

    /** @return TModel */
    public function firstRequired(): Model
    {
        $model = $this->first();

        if ($model === null) {
            throw new \RuntimeException('Expected at least one row, but the query returned none.');
        }

        return $model;
    }
}