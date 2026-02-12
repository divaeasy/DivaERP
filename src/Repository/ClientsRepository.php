<?php

namespace App\Repository;

use App\Entity\Clients;
use App\Model\SearchData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Clients>
 */
class ClientsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Clients::class);
    }

     /**
     * Get published posts thanks to Search Data value
     *
     * @param SearchData $searchData
     */
    public function findBySearch(SearchData $searchData)
    {
        $data = $this->createQueryBuilder('c')

            ->addOrderBy('c.nom', 'DESC');

        if (!empty($searchData->nom)) {
            $data = $data
                ->andWhere('c.nom LIKE :nom')
                ->setParameter('nom', "%{$searchData->nom}%");
        }

        if (!empty($searchData->tel)) {
            $data = $data
                ->andWhere('c.tel LIKE :tel')
                ->setParameter('tel', "%{$searchData->tel}%");
        }
      
        $data = $data
            ->getQuery()
            ->getResult();

        //$posts = $this->paginatorInterface->paginate($data, $searchData->page, 9);

        return $data;
    }

    //    /**
    //     * @return Clients[] Returns an array of Clients objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Clients
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
