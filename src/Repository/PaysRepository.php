<?php

namespace App\Repository;

use App\Entity\Pays;
use App\Model\SearchGeneric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Pays>
 */
class PaysRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pays::class);
    }

    public function findBySearch(SearchGeneric $searchData): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.libelle', 'ASC');

        if (!empty($searchData->q)) {
            $qb->andWhere('p.libelle LIKE :q')
               ->setParameter('q', "%{$searchData->q}%");
        }

        return $qb->getQuery()->getResult();
    }

    //    public function findOneBySomeField($value): ?Pays
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
