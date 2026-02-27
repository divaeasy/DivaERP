<?php

namespace App\Repository;

use App\Entity\Devises;
use App\Model\SearchGeneric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Devises>
 */
class DevisesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Devises::class);
    }

    public function findBySearch(SearchGeneric $searchData): array
    {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.code', 'ASC');

        if (!empty($searchData->q)) {
            $qb->andWhere('d.code LIKE :q OR d.libelle LIKE :q')
               ->setParameter('q', "%{$searchData->q}%");
        }

        return $qb->getQuery()->getResult();
    }

    //    public function findOneBySomeField($value): ?Devises
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
