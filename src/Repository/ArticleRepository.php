<?php

namespace App\Repository;

use App\Entity\Article;
use App\Model\SearchData;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Get published posts thanks to Search Data value
     *
     * @param SearchData $searchData
     */
    public function findBySearch(SearchData $searchData)
    {
        $data = $this->createQueryBuilder('a')

            ->addOrderBy('a.libelle', 'DESC');

        if (!empty($searchData->nom)) {
            $data = $data
                ->andWhere('a.libelle LIKE :libelle')
                ->setParameter('libelle', "%{$searchData->nom}%");
        }

        
        $data = $data
            ->getQuery()
            ->getResult();

        //$posts = $this->paginatorInterface->paginate($data, $searchData->page, 9);

        return $data;
    }

    //    /**
    //     * @return Article[] Returns an array of Article objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Article
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
