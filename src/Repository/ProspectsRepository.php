<?php

namespace App\Repository;

use App\Entity\Prospects;
use App\Model\SearchData;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prospects>
 */
class ProspectsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prospects::class);
    }

    public function getSearchQueryBuilder(?SearchData $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->addOrderBy('p.nom', 'ASC');

        if ($searchData) {
            if (!empty($searchData->nom)) {
                $qb->andWhere('p.nom LIKE :nom')
                   ->setParameter('nom', "%{$searchData->nom}%");
            }
            if (!empty($searchData->tel)) {
                $qb->andWhere('p.tel LIKE :tel')
                   ->setParameter('tel', "%{$searchData->tel}%");
            }
        }

        return $qb;
    }

    public function findBySearch(SearchData $searchData)
    {
        return $this->getSearchQueryBuilder($searchData)->getQuery()->getResult();
    }

    public function findPaginated(?SearchData $searchData = null, int $page = 1): array
    {
        $qb = $this->getSearchQueryBuilder($searchData);
        return PaginationHelper::paginate($qb, $page);
    }
}
