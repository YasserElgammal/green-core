<?php

namespace YasserElgammal\Green\Pagination;

class Paginator
{
    /**
     * @param array|\Doctrine\DBAL\Query\QueryBuilder $items
     * @param int $perPage
     * @param int $page
     * @return array
     */
    public function paginate(mixed $items, int $perPage, int $page): array
    {
        if ($items instanceof \Doctrine\DBAL\Query\QueryBuilder) {
            $countQuery = clone $items;
            $totalItems = (int) $countQuery->select('COUNT(*)')
                ->executeQuery()
                ->fetchOne();

            $totalPages = (int) ceil($totalItems / $perPage);
            $page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));

            $offset = ($page - 1) * $perPage;
            $data = $items->select('*')
                ->setFirstResult($offset)
                ->setMaxResults($perPage)
                ->executeQuery()
                ->fetchAllAssociative();
        } else {
            $totalItems = count($items);
            $totalPages = (int) ceil($totalItems / $perPage);
            $page = max(1, min($page, $totalPages > 0 ? $totalPages : 1));

            $offset = ($page - 1) * $perPage;
            $data = array_slice($items, $offset, $perPage);
        }

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
    }
}
