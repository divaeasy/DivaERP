<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;

class DashboardService
{
    public function __construct(private Connection $connection, private Security $security)
    {
    }

    /**
     * @return array<int>
     */
    public function getAvailableYears(): array
    {
        $params = [];
        $sql = "
            SELECT DISTINCT YEAR(ep.datep) AS y
            FROM entetepiece ep
            WHERE ep.datep IS NOT NULL
            ORDER BY y DESC
        ";

        $sql = $this->applyDossierFilter($sql, 'ep', $params);
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return array_values(array_map(static fn (array $row): int => (int) $row['y'], $result->fetchAllAssociative()));
    }

    public function getTotalRevenue(int $year, ?int $upToMonth = null): float
    {
        $startDate = "{$year}-01-01";
        if ($upToMonth !== null) {
            $lastDay = (int) (new \DateTime("{$year}-{$upToMonth}-01"))->format('t');
            $endDate = sprintf('%04d-%02d-%02d', $year, $upToMonth, $lastDay);
        } else {
            $endDate = "{$year}-12-31";
        }

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT SUM({$this->invoiceAmountExpression()}) AS total
            FROM entetepiece ep
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
        ";


        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return (float) ($result->fetchOne() ?? 0);
    }

    /**
     * @return array<int, float>
     */
    public function getMonthlySales(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT
                MONTH(ep.datep) AS month,
                SUM({$this->invoiceAmountExpression()}) AS amount
            FROM entetepiece ep
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
            GROUP BY MONTH(ep.datep)
            ORDER BY MONTH(ep.datep) ASC
        ";


        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        $sales = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $sales[(int) $row['month']] = (float) $row['amount'];
        }

        for ($i = 1; $i <= 12; $i++) {
            if (!isset($sales[$i])) {
                $sales[$i] = 0.0;
            }
        }

        ksort($sales);

        return $sales;
    }

    public function getYearGrowth(int $currentYear, int $previousYear, ?int $upToMonth = null): float
    {
        $currentRevenue = $this->getTotalRevenue($currentYear, $upToMonth);
        $previousRevenue = $this->getTotalRevenue($previousYear, $upToMonth);

        if ($previousRevenue == 0.0) {
            return $currentRevenue > 0 ? 100.0 : 0.0;
        }

        return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
    }

    public function getTotalInvoiceCount(int $year): int
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT COUNT(*) AS total
            FROM entetepiece ep
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
        ";

        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return (int) ($result->fetchOne() ?? 0);
    }

    public function getNewCustomersThisMonth(): int
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');
        $startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
        $endDate = sprintf('%04d-%02d-31', $currentYear, $currentMonth);

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT COUNT(DISTINCT ep.client_id) AS total
            FROM entetepiece ep
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
        ";

        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return (int) ($result->fetchOne() ?? 0);
    }

    public function getTotalProductsSold(int $year): int
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT SUM(lp.qte) AS total
            FROM lignepiece lp
            JOIN entetepiece ep ON lp.piece_id = ep.id
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
        ";

        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return (int) ($result->fetchOne() ?? 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTop5Products(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT
                a.libelle AS product_name,
                SUM(lp.qte) AS total_qty
            FROM lignepiece lp
            JOIN article a ON lp.article_id = a.id
            JOIN entetepiece ep ON lp.piece_id = ep.id
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
            GROUP BY a.id, a.libelle
            ORDER BY total_qty DESC
            LIMIT 5
        ";

        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return $result->fetchAllAssociative();
    }

    /**
     * Keep method name for compatibility with controllers/templates.
     * Here "category" is the type of piece (Devis/Commande/BL/Facture).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSalesByCategory(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT
                COALESCE(NULLIF(TRIM(ep.type), ''), 'Non renseigne') AS category,
                SUM({$this->invoiceAmountExpression()}) AS amount
            FROM entetepiece ep
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
            GROUP BY category
            ORDER BY amount DESC
        ";


        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return $result->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPaymentStatus(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        $today = date('Y-m-d');

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'today' => $today,
        ];

        $sql = "
            SELECT
                CASE
                    WHEN ep.delai IS NOT NULL AND ep.delai < :today THEN 'Late'
                    WHEN ep.delai IS NOT NULL THEN 'Pending'
                    ELSE 'No deadline'
                END AS status,
                SUM({$this->invoiceAmountExpression()}) AS amount,
                COUNT(*) AS count
            FROM entetepiece ep
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
            GROUP BY status

        ";

        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return $result->fetchAllAssociative();
    }

    /**
     * @return array{count:int,amount:float}
     */
    public function getOverdueInvoices(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        $today = date('Y-m-d');

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'today' => $today,
        ];

        $sql = "
            SELECT
                COUNT(*) AS count,
                COALESCE(SUM({$this->invoiceAmountExpression()}), 0) AS amount
            FROM entetepiece ep
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
                AND ep.delai IS NOT NULL
                AND ep.delai < :today

        ";

        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        $row = $result->fetchAssociative();

        return [
            'count' => (int) ($row['count'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{customers:int,sales:int}>
     */
    public function getCustomerGrowth(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT
                MONTH(ep.datep) AS month,
                COUNT(DISTINCT ep.client_id) AS unique_customers,
                COUNT(*) AS total_sales
            FROM entetepiece ep
            WHERE ep.datep >= :startDate
                AND ep.datep <= :endDate
            GROUP BY MONTH(ep.datep)
            ORDER BY MONTH(ep.datep) ASC
        ";

        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        $growth = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $growth[(int) $row['month']] = [
                'customers' => (int) $row['unique_customers'],
                'sales' => (int) $row['total_sales'],
            ];
        }

        for ($i = 1; $i <= 12; $i++) {
            if (!isset($growth[$i])) {
                $growth[$i] = ['customers' => 0, 'sales' => 0];
            }
        }

        ksort($growth);

        return $growth;
    }

    private function invoiceAmountExpression(): string
    {
        return 'COALESCE((SELECT SUM(lp.montant) FROM lignepiece lp WHERE lp.piece_id = ep.id), ep.montant, 0)';
    }

    private function applyDossierFilter(string $sql, string $alias, array &$params): string
    {
        $dossierId = $this->getCurrentDossierId();
        if ($dossierId === null) {
            return $sql;
        }

        $params['dossierId'] = $dossierId;
        $filter = sprintf(' AND %s.dossier_id = :dossierId', $alias);

        // Insert filter after JOIN ON condition before WHERE, or in WHERE patterns
        if (preg_match('/(ep\.id)(?=\s*WHERE)/is', $sql)) {
            $sql = preg_replace('/(ep\.id)(?=\s*WHERE)/is', '$1 ' . $filter, $sql, 1);
        } elseif (stripos($sql, ':endDate') !== false) {
            $sql = preg_replace('/(AND[ \\t\\n\\r]*ep\\.datep[ \\t\\n\\r]*<= [ \\t\\n\\r]*:endDate)/i', '$1' . $filter, $sql);
        } elseif (stripos($sql, 'ep.datep IS NOT NULL') !== false) {
            $sql = preg_replace('/(IS[ \\t\\n\\r]*NOT[ \\t\\n\\r]*NULL)/i', '$1' . $filter, $sql);
        }

        return $sql;
    }

    private function getCurrentDossierId(): ?int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        $dossier = $user->getCurrentDossier();

        return $dossier?->getId();
    }
}
