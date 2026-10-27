<?php

namespace App\Repository;

use App\Entity\TiersInterne;
use App\Entity\User;
use App\Model\SearchData;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<TiersInterne>
 */
class TiersInterneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private Security $security)
    {
        parent::__construct($registry, TiersInterne::class);
    }

    public function getSearchQueryBuilder(?SearchData $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->addOrderBy('t.nom', 'ASC');

        $this->applyDossierFilter($qb, 't');

        if ($searchData) {
            if ($searchData->nom !== '') {
                $qb->andWhere('t.nom LIKE :nom')
                    ->setParameter('nom', '%' . $searchData->nom . '%');
            }
            if ($searchData->tel !== '') {
                $qb->andWhere('t.tel LIKE :tel')
                    ->setParameter('tel', '%' . $searchData->tel . '%');
            }
        }

        return $qb;
    }

    public function findPaginated(?SearchData $searchData = null, int $page = 1): array
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
