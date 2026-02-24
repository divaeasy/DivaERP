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
        $currentYear = (int)date('Y');
        $targetYear = $currentYear - $annee;
        $startDate = "{$targetYear}-01-01";
        $endDate = "{$targetYear}-12-31";
        
        $sql = "
            SELECT 
                MONTH(ep.datep) as mois,
                SUM(ep.montant) as mont
            FROM entetepiece ep
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
            GROUP BY MONTH(ep.datep)
            ORDER BY MONTH(ep.datep) ASC
        ";
        
        $connection = $this->getEntityManager()->getConnection();
        $statement = $connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        return $result->fetchAllAssociative();
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
