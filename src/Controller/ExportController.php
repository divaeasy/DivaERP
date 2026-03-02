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
use App\Service\ExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/export')]
class ExportController extends AbstractController
{
    public function __construct(private ExportService $exportService) {}

    #[Route('/clients', name: 'export.clients')]
    public function exportClients(ClientsRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'clients_' . date('Y-m-d') . '.csv',
            ['ID', 'Nom', 'Adresse', 'Ville', 'Pays', 'Téléphone', 'Email', 'Tarif', 'Règlement'],
            $items,
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
            ]
        );
    }

    #[Route('/prospects', name: 'export.prospects')]
    public function exportProspects(ProspectsRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'prospects_' . date('Y-m-d') . '.csv',
            ['ID', 'Nom', 'Adresse', 'Ville', 'Pays', 'Téléphone', 'Email', 'Web', 'LinkedIn'],
            $items,
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
            ]
        );
    }

    #[Route('/articles', name: 'export.articles')]
    public function exportArticles(ArticleRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'articles_' . date('Y-m-d') . '.csv',
            ['ID', 'Désignation', 'Unité', 'Tarif'],
            $items,
            fn($a) => [
                $a->getId(),
                $a->getLibelle(),
                (string) $a->getUnite(),
                (string) $a->getTarif(),
            ]
        );
    }

    #[Route('/factures', name: 'export.factures')]
    public function exportFactures(EntetepieceRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'factures_' . date('Y-m-d') . '.csv',
            ['ID', 'Référence', 'Client', 'Date', 'Montant', 'Statut', 'Échéance'],
            $items,
            fn($e) => [
                $e->getId(),
                $e->getPieceref(),
                (string) $e->getClient(),
                $e->getDatep() ? $e->getDatep()->format('d/m/Y') : '',
                $e->getMontant(),
                $e->getStatut(),
                $e->getDelai() ? $e->getDelai()->format('d/m/Y') : '',
            ]
        );
    }

    #[Route('/devises', name: 'export.devises')]
    public function exportDevises(DevisesRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'devises_' . date('Y-m-d') . '.csv',
            ['ID', 'Code', 'Libellé'],
            $items,
            fn($d) => [$d->getId(), $d->getCode(), $d->getLibelle()]
        );
    }

    #[Route('/pays', name: 'export.pays')]
    public function exportPays(PaysRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'pays_' . date('Y-m-d') . '.csv',
            ['ID', 'Libellé'],
            $items,
            fn($p) => [$p->getId(), $p->getLibelle()]
        );
    }

    #[Route('/villes', name: 'export.villes')]
    public function exportVilles(VilleRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'villes_' . date('Y-m-d') . '.csv',
            ['ID', 'Libellé'],
            $items,
            fn($v) => [$v->getId(), $v->getLibelle()]
        );
    }

    #[Route('/unites', name: 'export.unites')]
    public function exportUnites(UniteRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'unites_' . date('Y-m-d') . '.csv',
            ['ID', 'Code', 'Libellé'],
            $items,
            fn($u) => [$u->getId(), $u->getCode(), $u->getLibelle()]
        );
    }

    #[Route('/tarifs', name: 'export.tarifs')]
    public function exportTarifs(TarifsRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'tarifs_' . date('Y-m-d') . '.csv',
            ['ID', 'Libellé'],
            $items,
            fn($t) => [$t->getId(), $t->getLibelle()]
        );
    }

    #[Route('/reglements', name: 'export.reglements')]
    public function exportReglements(ReglementRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'reglements_' . date('Y-m-d') . '.csv',
            ['ID', 'Libellé'],
            $items,
            fn($r) => [$r->getId(), $r->getLibelle()]
        );
    }

    #[Route('/dossiers', name: 'export.dossiers')]
    public function exportDossiers(DossierRepository $repo): StreamedResponse
    {
        $items = $repo->getSearchQueryBuilder()->getQuery()->getResult();
        return $this->exportService->exportCsv(
            'dossiers_' . date('Y-m-d') . '.csv',
            ['ID', 'Nom', 'Adresse'],
            $items,
            fn($d) => [$d->getId(), $d->getNom(), $d->getAdresse()]
        );
    }
}
