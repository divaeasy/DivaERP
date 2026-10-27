<?php

namespace App\Repository;

use App\Entity\Depot;
use App\Entity\User;
use App\Model\SearchGeneric;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Depot>
 */
class DepotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private Security $security)
    {
        parent::__construct($registry, Depot::class);
    }

    public function getSearchQueryBuilder(?SearchGeneric $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('d')
            ->addOrderBy('d.libelle', 'ASC');

        $this->applyDossierFilter($qb, 'd');

        if ($searchData && $searchData->q !== '') {
            $qb
                ->andWhere('d.libelle LIKE :q OR d.adr1 LIKE :q OR d.rue LIKE :q OR d.codepostal LIKE :q')
                ->setParameter('q', '%' . $searchData->q . '%');
        }

        return $qb;
    }

    public function findPaginated(?SearchGeneric $searchData = null, int $page = 1): array
    {
        return PaginationHelper::paginate($this->getSearchQueryBuilder($searchData), $page);
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
