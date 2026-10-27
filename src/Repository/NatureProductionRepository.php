<?php

namespace App\Repository;

use App\Entity\NatureProduction;
use App\Model\SearchGeneric;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NatureProduction>
 */
class NatureProductionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NatureProduction::class);
    }

    public function getSearchQueryBuilder(?SearchGeneric $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('n')
            ->orderBy('n.libelle', 'ASC');

        if ($searchData && $searchData->q !== '') {
            $qb
                ->andWhere('n.libelle LIKE :q OR n.type LIKE :q')
                ->setParameter('q', '%' . $searchData->q . '%');
        }

        return $qb;
    }

    public function findPaginated(?SearchGeneric $searchData = null, int $page = 1): array
    {
        return PaginationHelper::paginate($this->getSearchQueryBuilder($searchData), $page);
    }
}
