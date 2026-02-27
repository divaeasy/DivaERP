<?php

namespace App\Repository;

use App\Entity\Ville;
use App\Model\SearchGeneric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Ville>
 */
class VilleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ville::class);
    }

    public function findBySearch(SearchGeneric $searchData): array
    {
        $qb = $this->createQueryBuilder('v')
            ->orderBy('v.libelle', 'ASC');

        if (!empty($searchData->q)) {
            $qb->andWhere('v.libelle LIKE :q')
               ->setParameter('q', "%{$searchData->q}%");
        }

        return $qb->getQuery()->getResult();
    }

    //    public function findOneBySomeField($value): ?Ville
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
