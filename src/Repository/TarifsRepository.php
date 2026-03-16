<?php

namespace App\Repository;

use App\Entity\Tarifs;
use App\Entity\User;
use App\Model\SearchGeneric;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Tarifs>
 */
class TarifsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private Security $security)
    {
        parent::__construct($registry, Tarifs::class);
    }

    public function getSearchQueryBuilder(?SearchGeneric $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.libelle', 'ASC');

        $this->applyDossierFilter($qb, 't');

        if ($searchData && !empty($searchData->q)) {
            $qb->andWhere('t.libelle LIKE :q')
               ->setParameter('q', "%{$searchData->q}%");
        }

        return $qb;
    }

    public function findBySearch(SearchGeneric $searchData): array
    {
        return $this->getSearchQueryBuilder($searchData)->getQuery()->getResult();
    }

    public function findPaginated(?SearchGeneric $searchData = null, int $page = 1): array
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
