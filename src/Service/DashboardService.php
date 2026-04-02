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

        $sql = $this->applyFactureFilter($sql, 'ep');
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

        $sql = $this->applyFactureFilter($sql, 'ep');
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

        $sql = $this->applyFactureFilter($sql, 'ep');
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

        $sql = $this->applyFactureFilter($sql, 'ep');
        $sql = $this->applyDossierFilter($sql, 'ep', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return (int) ($result->fetchOne() ?? 0);
    }

    public function getTotalClients(): int
    {
        $params = [];
        $sql = "
            SELECT COUNT(*) AS total
            FROM clients c
        ";

        $sql = $this->applyDossierFilter($sql, 'c', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        return (int) ($result->fetchOne() ?? 0);
    }

    public function getNewCustomersThisMonth(): int
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('m');
        $startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
        $lastDay = (int) (new \DateTime($startDate))->format('t');
        $endDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $lastDay);

        $params = [
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];

        $sql = "
            SELECT COUNT(*) AS total
            FROM clients c
            WHERE DATE(c.created_at) >= :startDate
                AND DATE(c.created_at) <= :endDate
        ";

        $sql = $this->applyDossierFilter($sql, 'c', $params);

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

        $sql = $this->applyFactureFilter($sql, 'ep');
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

        $sql = $this->applyFactureFilter($sql, 'ep');
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

        $sql = $this->applyFactureFilter($sql, 'ep');
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

        $sql = $this->applyFactureFilter($sql, 'ep');
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
                MONTH(c.created_at) AS month,
                COUNT(*) AS total_customers
            FROM clients c
            WHERE DATE(c.created_at) >= :startDate
                AND DATE(c.created_at) <= :endDate
            GROUP BY MONTH(c.created_at)
            ORDER BY MONTH(c.created_at) ASC
        ";

        $sql = $this->applyDossierFilter($sql, 'c', $params);

        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery($params);

        $growth = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $growth[(int) $row['month']] = [
                'customers' => (int) $row['total_customers'],
                'sales' => 0,
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

    private function applyFactureFilter(string $sql, string $alias): string
    {
        $condition = sprintf("LOWER(TRIM(%s.type)) IN ('facture')", $alias);

        return $this->appendWhereCondition($sql, $condition);
    }

    private function applyDossierFilter(string $sql, string $alias, array &$params): string
    {
        $dossierId = $this->getCurrentDossierId();
        if ($dossierId === null) {
            return $sql;
        }

        $params['dossierId'] = $dossierId;
        $condition = sprintf('%s.dossier_id = :dossierId', $alias);

        return $this->appendWhereCondition($sql, $condition);
    }

    private function appendWhereCondition(string $sql, string $condition): string
    {
        $trimmedSql = rtrim($sql);
        $hasWhere = stripos($trimmedSql, 'WHERE') !== false;

        if (preg_match('/\b(GROUP BY|ORDER BY|LIMIT)\b/i', $trimmedSql, $match, PREG_OFFSET_CAPTURE)) {
            $position = $match[0][1];
            $head = rtrim(substr($trimmedSql, 0, $position));
            $tail = ltrim(substr($trimmedSql, $position));

            $head .= $hasWhere ? ' AND ' . $condition : ' WHERE ' . $condition;

            return $head . ' ' . $tail;
        }

        return $trimmedSql . ($hasWhere ? ' AND ' : ' WHERE ') . $condition;
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
