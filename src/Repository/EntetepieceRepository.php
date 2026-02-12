<?php

namespace App\Repository;

use App\Entity\Entetepiece;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entetepiece>
 */
class EntetepieceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entetepiece::class);
    }

    public function getCaParAnnee($annee): array
   {
       $fields = array('year(ep.datep)','month(ep.datep) as mois');
       return $this->createQueryBuilder('ep')
           ->select($fields)
           ->addSelect("SUM(ep.montant) As mont")
           //->groupBy('a.nom')
           //->addGroupBy('a.prenom')
           //->addGroupBy('an.annee')
           ->Where("YEAR(ep.datep) = YEAR(CURRENT_DATE()) - $annee ")
           ->groupBy('mois')
            //->addGroupBy("YEAR(ep.datep)")
           ->getQuery()
           ->getScalarResult()
       ;
    }

    //    /**
    //     * @return Entetepiece[] Returns an array of Entetepiece objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Entetepiece
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
