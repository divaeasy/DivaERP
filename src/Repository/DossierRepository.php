<?php

namespace App\Repository;

use App\Entity\Dossier;
use App\Model\SearchGeneric;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Dossier>
 */
class DossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Dossier::class);
    }

    public function findBySearch(SearchGeneric $searchData): array
    {
        $qb = $this->createQueryBuilder('d')
            ->orderBy('d.nom', 'ASC');

        if (!empty($searchData->q)) {
            $qb->andWhere('d.nom LIKE :q OR d.adresse LIKE :q')
               ->setParameter('q', "%{$searchData->q}%");
        }

        return $qb->getQuery()->getResult();
    }

    //    public function findOneBySomeField($value): ?Dossier
    //    {
    //        return $this->createQueryBuilder('d')
    //            ->andWhere('d.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
