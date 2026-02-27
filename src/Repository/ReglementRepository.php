<?php

namespace App\Repository;

use App\Entity\Reglement;
use App\Model\SearchGeneric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reglement>
 */
class ReglementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reglement::class);
    }

    public function findBySearch(SearchGeneric $searchData): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.libelle', 'ASC');

        if (!empty($searchData->q)) {
            $qb->andWhere('r.libelle LIKE :q')
               ->setParameter('q', "%{$searchData->q}%");
        }

        return $qb->getQuery()->getResult();
    }

    //    public function findOneBySomeField($value): ?Reglement
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
