<?php

namespace YasserElgammal\Green\Pagination;

use Doctrine\DBAL\Query\QueryBuilder;

class Paginator
{
    /**
     * @param array|QueryBuilder $items
     * @param int $perPage
     * @param int $page
     * @param bool $withCount
     * @return array
     */
    public function paginate(mixed $items, int $perPage, int $page, bool $withCount = true): array
    {
        $perPage = max(1, $perPage);
        $page = max(1, $page);

        if ($items instanceof QueryBuilder) {
            return $this->paginateQueryBuilder($items, $perPage, $page, $withCount);
        }

        return $this->paginateArray($items, $perPage, $page, $withCount);
    }

    private function paginateQueryBuilder(QueryBuilder $items, int $perPage, int $page, bool $withCount): array
    {
        [$totalItems, $totalPages, $page] = $this->resolveCountedMeta(
            $withCount,
            fn (): int => $this->countQueryBuilder($items),
            $perPage,
            $page
        );

        $rawData = $this->fetchQueryPage($items, $perPage, $page, $withCount);

        return $this->formatResult(
            $this->pageData($rawData, $perPage, $withCount),
            $page,
            $perPage,
            $totalItems,
            $totalPages,
            $this->hasNextPage($rawData, $perPage, $page, $totalPages, $withCount)
        );
    }

    /**
     * @param array<int, mixed> $items
     */
    private function paginateArray(array $items, int $perPage, int $page, bool $withCount): array
    {
        [$totalItems, $totalPages, $page] = $this->resolveCountedMeta(
            $withCount,
            fn (): int => count($items),
            $perPage,
            $page
        );

        $rawData = array_slice($items, $this->offset($page, $perPage), $this->limit($perPage, $withCount));

        return $this->formatResult(
            $this->pageData($rawData, $perPage, $withCount),
            $page,
            $perPage,
            $totalItems,
            $totalPages,
            $this->hasNextPage($rawData, $perPage, $page, $totalPages, $withCount)
        );
    }

    private function countQueryBuilder(QueryBuilder $items): int
    {
        $countQuery = clone $items;

        return (int) $countQuery
            ->select('COUNT(*)')
            ->setFirstResult(0)
            ->setMaxResults(null)
            ->resetOrderBy()
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @return array{0: int|null, 1: int|null, 2: int}
     */
    private function resolveCountedMeta(bool $withCount, callable $counter, int $perPage, int $page): array
    {
        if (!$withCount) {
            return [null, null, $page];
        }

        $totalItems = $counter();
        $totalPages = (int) ceil($totalItems / $perPage);

        return [$totalItems, $totalPages, $this->clampPage($page, $totalPages)];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchQueryPage(QueryBuilder $items, int $perPage, int $page, bool $withCount): array
    {
        $query = clone $items;

        return $query->select('*')
            ->setFirstResult($this->offset($page, $perPage))
            ->setMaxResults($this->limit($perPage, $withCount))
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function clampPage(int $page, int $totalPages): int
    {
        return max(1, min($page, $totalPages > 0 ? $totalPages : 1));
    }

    private function offset(int $page, int $perPage): int
    {
        return ($page - 1) * $perPage;
    }

    private function limit(int $perPage, bool $withCount): int
    {
        return $withCount ? $perPage : $perPage + 1;
    }

    /**
     * @param array<int, mixed> $rawData
     * @return array<int, mixed>
     */
    private function pageData(array $rawData, int $perPage, bool $withCount): array
    {
        return $withCount ? $rawData : array_slice($rawData, 0, $perPage);
    }

    /**
     * @param array<int, mixed> $rawData
     */
    private function hasNextPage(array $rawData, int $perPage, int $page, ?int $totalPages, bool $withCount): bool
    {
        return $withCount ? $page < $totalPages : count($rawData) > $perPage;
    }

    /**
     * @param array<int, mixed> $data
     */
    private function formatResult(
        array $data,
        int $page,
        int $perPage,
        ?int $totalItems,
        ?int $totalPages,
        bool $hasNext
    ): array {
        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'has_next' => $hasNext,
                'has_prev' => $page > 1,
            ],
        ];
    }
}
