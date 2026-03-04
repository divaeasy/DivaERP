<?php

namespace App\Repository;

use App\Entity\Tarifvente;
use App\Model\SearchGeneric;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tarifvente>
 */
class TarifventeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tarifvente::class);
    }

    public function getSearchQueryBuilder(?SearchGeneric $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC');

        if ($searchData && !empty($searchData->q)) {
            if (is_numeric($searchData->q)) {
                $qb->andWhere('t.prix = :prix')
                   ->setParameter('prix', (float)$searchData->q);
            }
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
