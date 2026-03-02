<?php

namespace App\Repository;

use App\Entity\Article;
use App\Model\SearchDataArt;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function getSearchQueryBuilder(?SearchDataArt $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->addOrderBy('a.libelle', 'ASC');

        if ($searchData && !empty($searchData->libelle)) {
            $qb->andWhere('a.libelle LIKE :libelle')
               ->setParameter('libelle', "%{$searchData->libelle}%");
        }

        return $qb;
    }

    public function findBySearch(SearchDataArt $searchData)
    {
        return $this->getSearchQueryBuilder($searchData)->getQuery()->getResult();
    }

    public function findPaginated(?SearchDataArt $searchData = null, int $page = 1): array
    {
        $qb = $this->getSearchQueryBuilder($searchData);
        return PaginationHelper::paginate($qb, $page);
    }
}
