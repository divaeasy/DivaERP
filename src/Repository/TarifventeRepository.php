<?php

namespace App\Repository;

use App\Entity\Tarifvente;
use App\Model\SearchGeneric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    public function findBySearch(SearchGeneric $searchData): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.id', 'DESC');

        if (!empty($searchData->q)) {
            if (is_numeric($searchData->q)) {
                $qb->andWhere('t.prix = :prix')
                   ->setParameter('prix', (float)$searchData->q);
            }
        }

        return $qb->getQuery()->getResult();
    }

    //    public function findOneBySomeField($value): ?Tarifvente
    //    {
    //        return $this->createQueryBuilder('t')
    //            ->andWhere('t.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
