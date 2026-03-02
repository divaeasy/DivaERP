<?php

namespace App\Repository;

use App\Entity\Tarifs;
use App\Model\SearchGeneric;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tarifs>
 */
class TarifsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarifs::class);
    }

    public function getSearchQueryBuilder(?SearchGeneric $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.libelle', 'ASC');

        if ($searchData && !empty($searchData->q)) {
            $qb->andWhere('t.libelle LIKE :q')
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
