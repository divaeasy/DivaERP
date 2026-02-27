<?php

namespace App\Controller;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashBordController extends AbstractController
{
    #[Route('/dashbord', name: 'app_dash_bord')]
    public function index(DashboardService $dashboardService): Response
    {
        $currentYear = (int)date('Y');
        $previousYear = $currentYear - 1;

        // Monthly sales data
        $monthlySalesCurrentYear = $dashboardService->getMonthlySales($currentYear);
        $monthlySalesPreviousYear = $dashboardService->getMonthlySales($previousYear);

        // KPI Data
        $totalRevenueCurrent = $dashboardService->getTotalRevenue($currentYear);
        $totalRevenuePerv = $dashboardService->getTotalRevenue($previousYear);
        $growthPercentage = $dashboardService->getYearGrowth($currentYear, $previousYear);
        $invoiceCount = $dashboardService->getTotalInvoiceCount($currentYear);
        $newCustomersThisMonth = $dashboardService->getNewCustomersThisMonth();
        $totalProductsSold = $dashboardService->getTotalProductsSold($currentYear);

        // Charts data
        $top5Products = $dashboardService->getTop5Products($currentYear);
        $salesByCategory = $dashboardService->getSalesByCategory($currentYear);
        $paymentStatus = $dashboardService->getPaymentStatus($currentYear);
        $customerGrowth = $dashboardService->getCustomerGrowth($currentYear);

        // Prepare data for templates
        $chartMonths = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'];
        $currentYearValues = array_values($monthlySalesCurrentYear);
        $previousYearValues = array_values($monthlySalesPreviousYear);

        // Top 5 products chart data
        $productNames = array_column($top5Products, 'product_name');
        $productQty = array_map(fn($p) => (float)$p['total_qty'], $top5Products);

        // Sales by category
        $categoryNames = array_column($salesByCategory, 'category');
        $categoryAmounts = array_map(fn($c) => (float)$c['amount'], $salesByCategory);

        // Payment status
        $paymentStatusLabels = array_column($paymentStatus, 'status');
        $paymentStatusAmounts = array_map(fn($p) => (float)$p['amount'], $paymentStatus);

        // Customer growth
        $customerCounts = [];
        for ($i = 1; $i <= 12; $i++) {
            $customerCounts[$i] = $customerGrowth[$i]['customers'] ?? 0;
        }

        return $this->render('dash_bord/index.html.twig', [
            'controller_name' => 'DashBordController',
            // KPI Data
            'totalRevenueCurrent' => $totalRevenueCurrent,
            'totalRevenuePerv' => $totalRevenuePerv,
            'growthPercentage' => round($growthPercentage, 2),
            'invoiceCount' => $invoiceCount,
            'newCustomersThisMonth' => $newCustomersThisMonth,
            'totalProductsSold' => $totalProductsSold,
            // Charts
            'chartMonths' => json_encode($chartMonths),
            'currentYearValues' => json_encode($currentYearValues),
            'previousYearValues' => json_encode($previousYearValues),
            'currentYear' => $currentYear,
            'previousYear' => $previousYear,
            'productNames' => json_encode($productNames),
            'productQty' => json_encode($productQty),
            'categoryNames' => json_encode($categoryNames),
            'categoryAmounts' => json_encode($categoryAmounts),
            'paymentStatusLabels' => json_encode($paymentStatusLabels),
            'paymentStatusAmounts' => json_encode($paymentStatusAmounts),
            'customerCounts' => json_encode(array_values($customerCounts)),
        ]);
    }
}
