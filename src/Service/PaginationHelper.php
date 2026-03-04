<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;

class PaginationHelper
{
    public const PER_PAGE = 15;

    /**
     * Paginate a query builder and return [items, totalItems, totalPages, currentPage]
     */
    public static function paginate(QueryBuilder $qb, int $page = 1, int $perPage = self::PER_PAGE): array
    {
        $page = max(1, $page);

        // Count total
        $countQb = clone $qb;
        $countQb->select('COUNT(' . $qb->getRootAliases()[0] . '.id)');
        // Remove any ordering for count query
        $countQb->resetDQLPart('orderBy');
        $totalItems = (int) $countQb->getQuery()->getSingleScalarResult();

        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $page = min($page, $totalPages);

        // Get paginated results
        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return [
            'items' => $items,
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $page,
        ];
    }
}
