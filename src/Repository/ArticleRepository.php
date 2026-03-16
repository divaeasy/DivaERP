<?php

namespace App\Repository;

use App\Entity\Article;
use App\Entity\User;
use App\Model\SearchDataArt;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private Security $security)
    {
        parent::__construct($registry, Article::class);
    }

    public function getSearchQueryBuilder(?SearchDataArt $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->addOrderBy('a.libelle', 'ASC');

        $this->applyDossierFilter($qb, 'a');

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

    private function applyDossierFilter(QueryBuilder $qb, string $alias): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        $currentDossier = $user->getCurrentDossier();
        if ($currentDossier !== null) {
            $qb->andWhere(sprintf('%s.dossier = :dossier', $alias))
               ->setParameter('dossier', $currentDossier);
        } else {
            $qb->andWhere('1 = 0');
        }
    }
}
