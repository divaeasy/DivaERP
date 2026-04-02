<?php

namespace App\Controller;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashBordController extends AbstractController
{
    #[Route('/dashbord', name: 'app_dash_bord')]
    public function index(Request $request, DashboardService $dashboardService): Response
    {
        $availableYears = $dashboardService->getAvailableYears();
        $fallbackYear = (int) date('Y');
        $currentYear = $availableYears[0] ?? $fallbackYear;
        $previousYear = $availableYears[1] ?? ($currentYear - 1);

        $comparisonYearCandidates = array_merge($availableYears, [$currentYear, $previousYear]);
        $comparisonYearMax = (int) max($comparisonYearCandidates);
        $comparisonYearMin = (int) min($comparisonYearCandidates);
        $comparisonYears = range($comparisonYearMax, $comparisonYearMin);

        $compareYearA = $request->query->getInt('compareYearA', $currentYear);
        $compareYearB = $request->query->getInt('compareYearB', $previousYear);

        if (!in_array($compareYearA, $comparisonYears, true)) {
            $compareYearA = $currentYear;
        }
        if (!in_array($compareYearB, $comparisonYears, true)) {
            $compareYearB = $previousYear;
        }
        if ($compareYearA === $compareYearB && count($comparisonYears) > 1) {
            $compareYearB = $comparisonYears[0] === $compareYearA ? $comparisonYears[1] : $comparisonYears[0];
        }

        $monthlySalesCurrentYear = $dashboardService->getMonthlySales($currentYear);
        $monthlySalesCompareYearA = $dashboardService->getMonthlySales($compareYearA);
        $monthlySalesCompareYearB = $dashboardService->getMonthlySales($compareYearB);

        $totalRevenueCurrent = $dashboardService->getTotalRevenue($currentYear);
        $totalRevenuePerv = $dashboardService->getTotalRevenue($previousYear);
        $growthPercentage = $dashboardService->getYearGrowth($currentYear, $previousYear);
        $invoiceCount = $dashboardService->getTotalInvoiceCount($currentYear);
        $totalClients = $dashboardService->getTotalClients();
        $newCustomersThisMonth = $dashboardService->getNewCustomersThisMonth();
        $totalProductsSold = $dashboardService->getTotalProductsSold($currentYear);
        $overdueInvoices = $dashboardService->getOverdueInvoices($currentYear);

        $top5Products = $dashboardService->getTop5Products($currentYear);
        $salesByCategory = $dashboardService->getSalesByCategory($currentYear);
        $paymentStatus = $dashboardService->getPaymentStatus($currentYear);
        $customerGrowth = $dashboardService->getCustomerGrowth($currentYear);

        $chartMonths = ['Jan', 'Fev', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aout', 'Sep', 'Oct', 'Nov', 'Dec'];
        $currentYearValues = array_values($monthlySalesCurrentYear);
        $compareYearAValues = array_values($monthlySalesCompareYearA);
        $compareYearBValues = array_values($monthlySalesCompareYearB);

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
            'totalClients' => $totalClients,
            'newCustomersThisMonth' => $newCustomersThisMonth,
            'totalProductsSold' => $totalProductsSold,
            'overdueInvoices' => $overdueInvoices,
            'currentMonth' => (int) date('m'),
            'chartMonths' => json_encode($chartMonths),
            'currentYearValues' => json_encode($currentYearValues),
            'compareYearAValues' => json_encode($compareYearAValues),
            'compareYearBValues' => json_encode($compareYearBValues),
            'currentYear' => $currentYear,
            'previousYear' => $previousYear,
            'compareYearA' => $compareYearA,
            'compareYearB' => $compareYearB,
            'productNames' => json_encode($productNames),
            'productQty' => json_encode($productQty),
            'categoryNames' => json_encode($categoryNames),
            'categoryAmounts' => json_encode($categoryAmounts),
            'paymentStatusLabels' => json_encode($paymentStatusLabels),
            'paymentStatusAmounts' => json_encode($paymentStatusAmounts),
            'customerCounts' => json_encode(array_values($customerCounts)),
            'availableYears' => $comparisonYears,
        ]);
    }
}

