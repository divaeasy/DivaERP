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
        $availableYears = $dashboardService->getAvailableYears();
        $fallbackYear = (int) date('Y');
        $currentYear = $availableYears[0] ?? $fallbackYear;
        $previousYear = $availableYears[1] ?? ($currentYear - 1);

        $monthlySalesCurrentYear = $dashboardService->getMonthlySales($currentYear);
        $monthlySalesPreviousYear = $dashboardService->getMonthlySales($previousYear);

        $totalRevenueCurrent = $dashboardService->getTotalRevenue($currentYear);
        $totalRevenuePerv = $dashboardService->getTotalRevenue($previousYear);
        $growthPercentage = $dashboardService->getYearGrowth($currentYear, $previousYear);
        $invoiceCount = $dashboardService->getTotalInvoiceCount($currentYear);
        $newCustomersThisMonth = $dashboardService->getNewCustomersThisMonth();
        $totalProductsSold = $dashboardService->getTotalProductsSold($currentYear);
        $overdueInvoices = $dashboardService->getOverdueInvoices($currentYear);

        $top5Products = $dashboardService->getTop5Products($currentYear);
        $salesByCategory = $dashboardService->getSalesByCategory($currentYear);
        $paymentStatus = $dashboardService->getPaymentStatus($currentYear);
        $customerGrowth = $dashboardService->getCustomerGrowth($currentYear);

        $chartMonths = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout', 'Sep', 'Oct', 'Nov', 'Dec'];
        $currentYearValues = array_values($monthlySalesCurrentYear);
        $previousYearValues = array_values($monthlySalesPreviousYear);

        $productNames = array_column($top5Products, 'product_name');
        $productQty = array_map(static fn ($p) => (float) $p['total_qty'], $top5Products);

        $categoryNames = array_column($salesByCategory, 'category');
        $categoryAmounts = array_map(static fn ($c) => (float) $c['amount'], $salesByCategory);

        $paymentStatusLabels = array_column($paymentStatus, 'status');
        $paymentStatusAmounts = array_map(static fn ($p) => (float) $p['amount'], $paymentStatus);

        $customerCounts = [];
        for ($i = 1; $i <= 12; $i++) {
            $customerCounts[$i] = $customerGrowth[$i]['customers'] ?? 0;
        }

        return $this->render('dash_bord/index.html.twig', [
            'controller_name' => 'DashBordController',
            'totalRevenueCurrent' => $totalRevenueCurrent,
            'totalRevenuePerv' => $totalRevenuePerv,
            'growthPercentage' => round($growthPercentage, 2),
            'invoiceCount' => $invoiceCount,
            'newCustomersThisMonth' => $newCustomersThisMonth,
            'totalProductsSold' => $totalProductsSold,
            'overdueInvoices' => $overdueInvoices,
            'currentMonth' => (int) date('m'),
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
            'availableYears' => $availableYears,
        ]);
    }
}

