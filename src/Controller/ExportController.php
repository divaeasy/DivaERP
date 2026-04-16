<?php

namespace App\Controller;

use App\Repository\ArticleRepository;
use App\Repository\ClientsRepository;
use App\Repository\DevisesRepository;
use App\Repository\DossierRepository;
use App\Repository\EntetepieceRepository;
use App\Repository\PaysRepository;
use App\Repository\ProspectsRepository;
use App\Repository\ReglementRepository;
use App\Repository\TarifsRepository;
use App\Repository\TarifventeRepository;
use App\Repository\UniteRepository;
use App\Repository\VilleRepository;
use App\Service\DashboardService;
use App\Service\ExportService;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/export')]
class ExportController extends AbstractController
{
    public function __construct(private ExportService $exportService) {}

    #[Route('/clients', name: 'export.clients')]
    public function exportClients(ClientsRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Nom', 'Adresse', 'Ville', 'Pays', 'Telephone', 'Email', 'Tarif', 'Reglement'];
        $rows = array_map(
            fn($c) => [
                $c->getId(),
                $c->getNom(),
                $c->getAdr1(),
                (string) $c->getVille(),
                (string) $c->getPays(),
                $c->getTel(),
                $c->getEmail(),
                (string) $c->getTarif(),
                (string) $c->getReglement(),
            ],
            $items
        );
        return $this->exportService->exportListToExcel(
            'clients_' . date('Y-m-d_His') . '.xlsx',
            'Clients',
            $headers,
            $rows
        );
    }

    #[Route('/prospects', name: 'export.prospects')]
    public function exportProspects(ProspectsRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Nom', 'Adresse', 'Ville', 'Pays', 'Telephone', 'Email', 'Web', 'LinkedIn'];
        $rows = array_map(
            fn($p) => [
                $p->getId(),
                $p->getNom(),
                $p->getAdr1(),
                (string) $p->getVille(),
                (string) $p->getPays(),
                $p->getTel(),
                $p->getEmail(),
                $p->getWeb(),
                $p->getLinkedin(),
            ],
            $items
        );
        return $this->exportService->exportListToExcel(
            'prospects_' . date('Y-m-d_His') . '.xlsx',
            'Prospects',
            $headers,
            $rows
        );
    }

    #[Route('/articles', name: 'export.articles')]
    public function exportArticles(
        ArticleRepository $repo,
        UniteRepository $uniteRepository,
        TarifsRepository $tarifsRepository
    ): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $unites = $uniteRepository->findBy([], ['libelle' => 'ASC']);
        $tarifs = $tarifsRepository->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', "D\u{00E9}signation", "Unit\u{00E9}", 'Tarif'];
        $rows = array_map(
            fn($a) => [
                $a->getId(),
                $a->getLibelle(),
                (string) $a->getUnite(),
                (string) $a->getTarif(),
            ],
            $items
        );
        return $this->exportService->exportListToExcel(
            'articles_' . date('Y-m-d_His') . '.xls',
            'Articles',
            $headers,
            $rows,
            [
                ['title' => 'ID', 'value' => "Ne pas modifier. Colonne prot\u{00E9}g\u{00E9}e. Pr\u{00E9}sence d un ID existant = mise \u{00E0} jour."],
                ['title' => "D\u{00E9}signation", 'value' => "Obligatoire pour cr\u{00E9}ation (ID vide)."],
                ['title' => "Unit\u{00E9}", 'value' => "Optionnel. Choisissez une valeur de la liste Excel ou saisissez le libell\u{00E9} ou code exact de l unit\u{00E9}."],
                ['title' => 'Tarif', 'value' => "Optionnel. Choisissez une valeur de la liste Excel ou saisissez le libell\u{00E9} exact du tarif."],
                ['title' => 'Mode import', 'value' => "Ligne sans ID = cr\u{00E9}ation. Ligne avec ID = mise \u{00E0} jour."],
                ['title' => "Feuille \u{00E0} importer", 'value' => 'Ne modifiez que la feuille Export. La feuille Notices est informative.'],
            ],
            [
                'template' => 'articles_import',
                'include_footer' => false,
                'format' => 'xls',
                'article_unite_options' => array_values(array_filter(array_map(
                    static fn($unite) => trim((string) $unite),
                    $unites
                ))),
                'article_tarif_options' => array_values(array_filter(array_map(
                    static fn($tarif) => trim((string) $tarif),
                    $tarifs
                ))),
            ]
        );
    }

    #[Route('/factures', name: 'export.factures')]
    public function exportFactures(EntetepieceRepository $repo, ManagerRegistry $doctrine): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Référence', 'Tiers', 'Date', 'Montant', 'Statut', 'Échéance'];
        $rows = array_map(
            fn($e) => [
                $e->getId(),
                $e->getPieceref(),
                (string) $e->getTierName($doctrine),
                $e->getDatep() ? $e->getDatep()->format('d/m/Y') : '',
                $e->getMontant(),
                $e->getStatut(),
                $e->getDelai() ? $e->getDelai()->format('d/m/Y') : '',
            ],
            $items
        );
        return $this->exportService->exportListToExcel(
            'factures_' . date('Y-m-d_His') . '.xlsx',
            'Factures',
            $headers,
            $rows
        );
    }

    #[Route('/devises', name: 'export.devises')]
    public function exportDevises(DevisesRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Code', 'Libellé'];
        $rows = array_map(
            fn($d) => [$d->getId(), $d->getCode(), $d->getLibelle()],
            $items
        );
        return $this->exportService->exportListToExcel(
            'devises_' . date('Y-m-d_His') . '.xlsx',
            'Devises',
            $headers,
            $rows
        );
    }

    #[Route('/pays', name: 'export.pays')]
    public function exportPays(PaysRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Libellé'];
        $rows = array_map(
            fn($p) => [$p->getId(), $p->getLibelle()],
            $items
        );
        return $this->exportService->exportListToExcel(
            'pays_' . date('Y-m-d_His') . '.xlsx',
            'Pays',
            $headers,
            $rows
        );
    }

    #[Route('/villes', name: 'export.villes')]
    public function exportVilles(VilleRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Libellé'];
        $rows = array_map(
            fn($v) => [$v->getId(), $v->getLibelle()],
            $items
        );
        return $this->exportService->exportListToExcel(
            'villes_' . date('Y-m-d_His') . '.xlsx',
            'Villes',
            $headers,
            $rows
        );
    }

    #[Route('/unites', name: 'export.unites')]
    public function exportUnites(UniteRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Code', 'Libellé'];
        $rows = array_map(
            fn($u) => [$u->getId(), $u->getCode(), $u->getLibelle()],
            $items
        );
        return $this->exportService->exportListToExcel(
            'unites_' . date('Y-m-d_His') . '.xlsx',
            'UnitÃ©s',
            $headers,
            $rows
        );
    }

    #[Route('/tarifs', name: 'export.tarifs')]
    public function exportTarifs(TarifsRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Libellé'];
        $rows = array_map(
            fn($t) => [$t->getId(), $t->getLibelle()],
            $items
        );
        return $this->exportService->exportListToExcel(
            'tarifs_' . date('Y-m-d_His') . '.xlsx',
            'Tarifs',
            $headers,
            $rows
        );
    }

    #[Route('/reglements', name: 'export.reglements')]
    public function exportReglements(ReglementRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Libellé'];
        $rows = array_map(
            fn($r) => [$r->getId(), $r->getLibelle()],
            $items
        );
        return $this->exportService->exportListToExcel(
            'reglements_' . date('Y-m-d_His') . '.xlsx',
            'Règlements',
            $headers,
            $rows
        );
    }

    #[Route('/dossiers', name: 'export.dossiers')]
    public function exportDossiers(DossierRepository $repo): BinaryFileResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        $headers = ['ID', 'Nom', 'Adresse'];
        $rows = array_map(
            fn($d) => [$d->getId(), $d->getNom(), $d->getAdresse()],
            $items
        );
        return $this->exportService->exportListToExcel(
            'dossiers_' . date('Y-m-d_His') . '.xlsx',
            'Dossiers',
            $headers,
            $rows
        );
    }

    #[Route('/dashboard-excel', name: 'export.dashboard.excel')]
    public function exportDashboardExcel(DashboardService $dashboardService): BinaryFileResponse 
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');

        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');
        $previousYear = $currentYear - 1;
        
        $revenueCurrent = (float)$dashboardService->getTotalRevenue($currentYear, $currentMonth);
        $revenuePrevious = (float)$dashboardService->getTotalRevenue($previousYear, $currentMonth);
        $revenueGrowth = ($revenuePrevious != 0) 
            ? (($revenueCurrent - $revenuePrevious) / $revenuePrevious) * 100 
            : 0;
        $revenueGrowthStr = ($revenueGrowth >= 0 ? '+' : '') . number_format($revenueGrowth, 1) . '%';

        $overdue = $dashboardService->getOverdueInvoices($currentYear);
        $overdueAmount = (float)($overdue['amount'] ?? 0);

        $kpiData = [
            'Chiffre d\'affaires' => [
                'value' => number_format($revenueCurrent, 2, ',', ' ') . ' EUR',
                'period' => 'Jan - ' . ucfirst(strftime('%B %Y', mktime(0, 0, 0, $currentMonth, 1))),
                'comparison' => $revenueGrowthStr
            ],
            'Nombre de factures' => [
                'value' => (string)$dashboardService->getTotalInvoiceCount($currentYear),
                'period' => 'AnnÃ©e ' . $currentYear,
                'comparison' => '+0'
            ],
            'Nouveaux clients' => [
                'value' => (string)$dashboardService->getNewCustomersThisMonth(),
                'period' => ucfirst(strftime('%B %Y', time())),
                'comparison' => '+0'
            ],
            'Produits vendus' => [
                'value' => (string)$dashboardService->getTotalProductsSold($currentYear) . ' unitÃ©s',
                'period' => 'AnnÃ©e ' . $currentYear,
                'comparison' => '+0'
            ],
            'Factures en retard' => [
                'value' => ($overdue['count'] ?? 0) . ' factures - ' . number_format($overdueAmount, 2, ',', ' ') . ' EUR',
                'period' => 'Ã‰tat actuel',
                'comparison' => ($overdueAmount > 0 ? '-' : '+') . '0'
            ],
        ];

        return $this->exportService->exportDashboardToExcel($kpiData, 'tableau_de_bord_' . date('Y-m-d_His') . '.xlsx');
    }

    #[Route('/dashboard-pdf', name: 'export.dashboard.pdf')]
    public function exportDashboardPdf(DashboardService $dashboardService): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED');

        $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');
        $previousYear = $currentYear - 1;
        $overdue = $dashboardService->getOverdueInvoices($currentYear);

        $kpiData = [
            'Chiffre d\'affaires' => [
                'value' => 'â‚¬ ' . number_format((float)$dashboardService->getTotalRevenue($currentYear, $currentMonth), 2, '.', ','),
                'period' => 'Jan - ' . date('M Y'),
                'comparison' => ($dashboardService->getTotalRevenue($currentYear, $currentMonth) > $dashboardService->getTotalRevenue($previousYear, $currentMonth) ? '+' : '') . round((($dashboardService->getTotalRevenue($currentYear, $currentMonth) - $dashboardService->getTotalRevenue($previousYear, $currentMonth)) / (($dashboardService->getTotalRevenue($previousYear, $currentMonth) ?: 1)) * 100), 1) . '%'
            ],
            'Nombre de factures' => ['value' => (string)$dashboardService->getTotalInvoiceCount($currentYear), 'period' => 'AnnÃ©e ' . $currentYear, 'comparison' => '+0'],
            'Nouveaux clients' => ['value' => (string)$dashboardService->getNewCustomersThisMonth(), 'period' => date('F Y'), 'comparison' => '+0'],
            'Produits vendus' => ['value' => (string)$dashboardService->getTotalProductsSold($currentYear), 'period' => 'AnnÃ©e ' . $currentYear, 'comparison' => '+0'],
            'Factures en retard' => [
                'value' => sprintf('%d factures | %s EUR', $overdue['count'] ?? 0, number_format((float)($overdue['amount'] ?? 0), 2, '.', ',')),
                'period' => 'Actuel',
                'comparison' => '+0'
            ],
        ];

        $top5Products = $dashboardService->getTop5Products($currentYear) ?? [];

        return $this->exportService->exportDashboardToPdf($kpiData, is_array($top5Products) ? $top5Products : [], 'tableau_de_bord_' . date('Y-m-d') . '.pdf');
    }
}


