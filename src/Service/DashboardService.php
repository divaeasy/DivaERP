<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use DateTime;

class DashboardService
{
    public function __construct(private Connection $connection) {}

    /**
     * Get total revenue for a specific year (optionally up to a specific month for same-period comparison)
     */
    public function getTotalRevenue(int $year, ?int $upToMonth = null): float
    {
        $startDate = "{$year}-01-01";
        if ($upToMonth !== null) {
            $lastDay = (int)(new \DateTime("{$year}-{$upToMonth}-01"))->format('t');
            $endDate = sprintf('%04d-%02d-%02d', $year, $upToMonth, $lastDay);
        } else {
            $endDate = "{$year}-12-31";
        }
        
        $sql = "
            SELECT SUM(ep.montant) as total
            FROM entetepiece ep
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        return (float)($result->fetchOne() ?? 0);
    }

    /**
     * Get monthly sales for a specific year
     */
    public function getMonthlySales(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        
        $sql = "
            SELECT 
                MONTH(ep.datep) as month,
                SUM(ep.montant) as amount
            FROM entetepiece ep
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
            GROUP BY MONTH(ep.datep)
            ORDER BY MONTH(ep.datep) ASC
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        $sales = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $sales[(int)$row['month']] = (float)$row['amount'];
        }
        
        // Fill in missing months with 0
        for ($i = 1; $i <= 12; $i++) {
            if (!isset($sales[$i])) {
                $sales[$i] = 0;
            }
        }
        
        ksort($sales);
        return $sales;
    }

    /**
     * Get growth percentage comparing same period (Jan to current month) year-over-year
     */
    public function getYearGrowth(int $currentYear, int $previousYear): float
    {
        $currentMonth = (int)date('m');
        $currentRevenue = $this->getTotalRevenue($currentYear, $currentMonth);
        $previousRevenue = $this->getTotalRevenue($previousYear, $currentMonth);
        
        if ($previousRevenue == 0) {
            return $currentRevenue > 0 ? 100 : 0;
        }
        
        return (($currentRevenue - $previousRevenue) / $previousRevenue) * 100;
    }

    /**
     * Get total invoice count for year
     */
    public function getTotalInvoiceCount(int $year): int
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        
        $sql = "
            SELECT COUNT(*) as total
            FROM entetepiece ep
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        return (int)($result->fetchOne() ?? 0);
    }

    /**
     * Get new customers this month
     */
    public function getNewCustomersThisMonth(): int
    {
        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');
        $startDate = sprintf('%04d-%02d-01', $currentYear, $currentMonth);
        $endDate = sprintf('%04d-%02d-31', $currentYear, $currentMonth);
        
        $sql = "
            SELECT COUNT(DISTINCT ep.client_id) as total
            FROM entetepiece ep
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        return (int)($result->fetchOne() ?? 0);
    }

    /**
     * Get total products sold for year
     */
    public function getTotalProductsSold(int $year): int
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        
        $sql = "
            SELECT SUM(lp.qte) as total
            FROM lignepiece lp
            JOIN entetepiece ep ON lp.piece_id = ep.id
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        return (int)($result->fetchOne() ?? 0);
    }

    /**
     * Get top 5 products by quantity sold
     */
    public function getTop5Products(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        
        $sql = "
            SELECT 
                a.libelle as product_name,
                SUM(lp.qte) as total_qty
            FROM lignepiece lp
            JOIN article a ON lp.article_id = a.id
            JOIN entetepiece ep ON lp.piece_id = ep.id
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
            GROUP BY a.id, a.libelle
            ORDER BY total_qty DESC
            LIMIT 5
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        return $result->fetchAllAssociative();
    }

    /**
     * Get sales by category/dossierfamily (using dossier as proxy for category)
     * This returns sales distribution by dossier
     */
    public function getSalesByCategory(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        
        $sql = "
            SELECT 
                d.nom as category,
                SUM(ep.montant) as amount
            FROM entetepiece ep
            JOIN dossier d ON ep.dossier_id = d.id
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
            GROUP BY d.id, d.nom
            ORDER BY amount DESC
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        return $result->fetchAllAssociative();
    }

    /**
     * Get payment status breakdown
     */
    public function getPaymentStatus(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        $today = date('Y-m-d');
        
        $sql = "
            SELECT 
                CASE 
                    WHEN ep.delai IS NOT NULL AND ep.delai < :today THEN 'Late'
                    WHEN ep.delai IS NOT NULL THEN 'Pending'
                    ELSE 'No deadline'
                END as status,
                SUM(ep.montant) as amount,
                COUNT(*) as count
            FROM entetepiece ep
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
            GROUP BY status
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'today' => $today,
        ]);
        
        return $result->fetchAllAssociative();
    }

    /**
     * Get overdue invoices (where deadline has passed)
     */
    public function getOverdueInvoices(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        $today = date('Y-m-d');
        
        $sql = "
            SELECT 
                COUNT(*) as count,
                COALESCE(SUM(ep.montant), 0) as amount
            FROM entetepiece ep
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
                AND ep.delai IS NOT NULL
                AND ep.delai < :today
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'today' => $today,
        ]);
        
        $row = $result->fetchAssociative();
        return [
            'count' => (int)($row['count'] ?? 0),
            'amount' => (float)($row['amount'] ?? 0),
        ];
    }

    /**
     * Get customer growth over months
     */
    public function getCustomerGrowth(int $year): array
    {
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
        
        $sql = "
            SELECT 
                MONTH(ep.datep) as month,
                COUNT(DISTINCT ep.client_id) as unique_customers,
                COUNT(*) as total_sales
            FROM entetepiece ep
            WHERE ep.datep >= :startDate 
                AND ep.datep <= :endDate
            GROUP BY MONTH(ep.datep)
            ORDER BY MONTH(ep.datep) ASC
        ";
        
        $statement = $this->connection->prepare($sql);
        $result = $statement->executeQuery([
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
        
        $growth = [];
        foreach ($result->fetchAllAssociative() as $row) {
            $growth[(int)$row['month']] = [
                'customers' => (int)$row['unique_customers'],
                'sales' => (int)$row['total_sales'],
            ];
        }
        
        // Fill in missing months
        for ($i = 1; $i <= 12; $i++) {
            if (!isset($growth[$i])) {
                $growth[$i] = ['customers' => 0, 'sales' => 0];
            }
        }
        
        ksort($growth);
        return $growth;
    }
}
