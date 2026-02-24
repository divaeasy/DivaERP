<?php

namespace App\Repository;

use App\Entity\Prospects;
use App\Model\SearchData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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

    /**
     * Search prospects by name and phone
     *
     * @param SearchData $searchData
     */
    public function findBySearch(SearchData $searchData)
    {
        $data = $this->createQueryBuilder('p')
            ->addOrderBy('p.nom', 'DESC');

        if (!empty($searchData->nom)) {
            $data = $data
                ->andWhere('p.nom LIKE :nom')
                ->setParameter('nom', "%{$searchData->nom}%");
        }

        if (!empty($searchData->tel)) {
            $data = $data
                ->andWhere('p.tel LIKE :tel')
                ->setParameter('tel', "%{$searchData->tel}%");
        }

        $data = $data
            ->getQuery()
            ->getResult();

        return $data;
    }

    //    /**
    //     * @return Prospects[] Returns an array of Prospects objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Prospects
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
