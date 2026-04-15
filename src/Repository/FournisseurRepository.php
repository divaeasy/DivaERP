<?php

namespace App\Repository;

use App\Entity\Fournisseur;
use App\Entity\User;
use App\Model\SearchData;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Fournisseur>
 */
class FournisseurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private Security $security)
    {
        parent::__construct($registry, Fournisseur::class);
    }

    public function getSearchQueryBuilder(?SearchData $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('f')
            ->addOrderBy('f.nom', 'ASC');

        $this->applyDossierFilter($qb, 'f');

        if ($searchData) {
            if (!empty($searchData->nom)) {
                $qb->andWhere('f.nom LIKE :nom')
                    ->setParameter('nom', "%{$searchData->nom}%");
            }
            if (!empty($searchData->tel)) {
                $qb->andWhere('f.tel LIKE :tel')
                    ->setParameter('tel', "%{$searchData->tel}%");
            }
        }

        return $qb;
    }

    public function findBySearch(SearchData $searchData): array
    {
        return $this->getSearchQueryBuilder($searchData)->getQuery()->getResult();
    }

    public function findPaginated(?SearchData $searchData = null, int $page = 1): array
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
