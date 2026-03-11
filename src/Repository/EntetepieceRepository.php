<?php

namespace App\Repository;

use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Model\SearchPiece;
use App\Service\PaginationHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entetepiece>
 */
class EntetepieceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entetepiece::class);
    }

    public function getCaParAnnee($annee): array
    {
        $currentYear = (int)date('Y');
        $targetYear = $currentYear - $annee;
        $startDate = "{$targetYear}-01-01";
        $endDate = "{$targetYear}-12-31";
        
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
        
        $connection = $this->getEntityManager()->getConnection();
        $statement = $connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        return $result->fetchAllAssociative();
    }

    public function getSearchQueryBuilder(?SearchPiece $searchData = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->orderBy('e.id', 'DESC');

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
}
