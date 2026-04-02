<?php

namespace App\Repository;

use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\User;
use App\Model\SearchPiece;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Entetepiece>
 */
class EntetepieceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private Security $security)
    {
        parent::__construct($registry, Entetepiece::class);
    }

    public function getCaParAnnee($annee): array
    {
        $currentYear = (int)date('Y');
        $targetYear = $currentYear - $annee;
        $startDate = "{$targetYear}-01-01";
        $endDate = "{$targetYear}-12-31";

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

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

        $sql = $this->applyDossierFilterSql($sql, 'ep', $params);
        
        $connection = $this->getEntityManager()->getConnection();
        $statement = $connection->prepare($sql);
        $result = $statement->executeQuery($params);
        
        return $result->fetchAllAssociative();
    }

    public function getSearchQueryBuilder(?SearchPiece $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.id', 'DESC');

        $this->applyDossierFilter($qb, 'e');

        if ($searchData && !empty($searchData->pieceref)) {
            $qb->andWhere('e.pieceref LIKE :ref')
               ->setParameter('ref', "%{$searchData->pieceref}%");
        }

        if ($searchData && !empty($searchData->statut)) {
            $qb->andWhere('e.statut LIKE :statut')
               ->setParameter('statut', "%{$searchData->statut}%");
        }

        return $qb;
    }

    public function findBySearch(SearchPiece $searchData): array
    {
        return $this->getSearchQueryBuilder($searchData)->getQuery()->getResult();
    }

    public function findPaginated(?SearchPiece $searchData = null, int $page = 1): array
    {
        $qb = $this->getSearchQueryBuilder($searchData);
        return PaginationHelper::paginate($qb, $page);
    }

    /**
     * Compute weighted average remise per invoice based on line items.
     *
     * @param array<int> $invoiceIds
     * @return array<int, float> Map of invoiceId => remise percentage
     */
    public function getWeightedRemiseByInvoiceIds(array $invoiceIds): array
    {
        $invoiceIds = array_values(array_filter(array_map('intval', $invoiceIds)));
        if ($invoiceIds === []) {
            return [];
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('IDENTITY(lp.piece) AS invoice_id')
            ->addSelect('SUM(COALESCE(lp.qte, 0) * COALESCE(lp.pub, 0)) AS base_total')
            ->addSelect('SUM(COALESCE(lp.qte, 0) * COALESCE(lp.pub, 0) * COALESCE(lp.remise, 0)) AS remise_weighted')
            ->from(Lignepiece::class, 'lp')
            ->where($qb->expr()->in('lp.piece', ':ids'))
            ->setParameter('ids', $invoiceIds)
            ->groupBy('lp.piece');

        $rows = $qb->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $row) {
            $invoiceId = (int) ($row['invoice_id'] ?? 0);
            $baseTotal = (float) ($row['base_total'] ?? 0);
            $weighted = (float) ($row['remise_weighted'] ?? 0);
            $result[$invoiceId] = $baseTotal > 0 ? ($weighted / $baseTotal) : 0.0;
        }

        return $result;
    }

    /**
     * @param array<int> $invoiceIds
     * @return array<int, float> Map of invoiceId => total amount
     */
    public function getTotalAmountByInvoiceIds(array $invoiceIds): array
    {
        $invoiceIds = array_values(array_filter(array_map('intval', $invoiceIds)));
        if ($invoiceIds === []) {
            return [];
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('IDENTITY(lp.piece) AS invoice_id')
            ->addSelect('COALESCE(SUM((COALESCE(lp.qte, 0) * COALESCE(lp.pub, 0)) * (1 - (COALESCE(lp.remise, 0) / 100))), 0) AS total_amount')
            ->from(Lignepiece::class, 'lp')
            ->where($qb->expr()->in('lp.piece', ':ids'))
            ->setParameter('ids', $invoiceIds)
            ->groupBy('lp.piece');

        $rows = $qb->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) ($row['invoice_id'] ?? 0)] = (float) ($row['total_amount'] ?? 0.0);
        }

        return $result;
    }

    /**
     * @param array<int> $invoiceIds
     * @return array<int, int> Map of invoiceId => line count
     */
    public function getLineCountByInvoiceIds(array $invoiceIds): array
    {
        $invoiceIds = array_values(array_filter(array_map('intval', $invoiceIds)));
        if ($invoiceIds === []) {
            return [];
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb->select('IDENTITY(lp.piece) AS invoice_id')
            ->addSelect('COUNT(lp.id) AS line_count')
            ->from(Lignepiece::class, 'lp')
            ->where($qb->expr()->in('lp.piece', ':ids'))
            ->setParameter('ids', $invoiceIds)
            ->groupBy('lp.piece');

        $rows = $qb->getQuery()->getArrayResult();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) ($row['invoice_id'] ?? 0)] = (int) ($row['line_count'] ?? 0);
        }

        return $result;
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

    private function applyDossierFilterSql(string $sql, string $alias, array &$params): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $sql;
        }

        $filter = ' AND 1 = 0';
        $currentDossier = $user->getCurrentDossier();
        if ($currentDossier !== null) {
            $params['dossierId'] = $currentDossier->getId();
            $filter = sprintf(' AND %s.dossier_id = :dossierId', $alias);
        }

        if (preg_match('/\b(GROUP BY|ORDER BY|LIMIT)\b/i', $sql, $match, PREG_OFFSET_CAPTURE)) {
            $pos = $match[0][1];
            return substr($sql, 0, $pos) . $filter . ' ' . substr($sql, $pos);
        }

        return $sql . $filter;
    }
}
