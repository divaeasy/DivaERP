<?php

namespace App\Repository;

use App\Entity\Unite;
use App\Model\SearchGeneric;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Unite>
 */
class UniteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Unite::class);
    }

    public function getSearchQueryBuilder(?SearchGeneric $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.libelle', 'ASC');

        if ($searchData && !empty($searchData->q)) {
            $qb->andWhere('u.code LIKE :q OR u.libelle LIKE :q')
               ->setParameter('q', "%{$searchData->q}%");
        }

        return $qb;
    }

    public function findBySearch(SearchGeneric $searchData): array
    {
        return $this->getSearchQueryBuilder($searchData)->getQuery()->getResult();
    }

    public function findPaginated(?SearchGeneric $searchData = null, int $page = 1): array
    {
        $qb = $this->getSearchQueryBuilder($searchData);
        return PaginationHelper::paginate($qb, $page);
    }
}
