<?php

namespace App\Controller;

use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\User;
use App\Form\EntetePieceFormType;
use App\Form\SearchPieceFormType;
use App\Model\SearchPiece;
use App\Repository\EntetepieceRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('piece')]
class EntetePController extends AbstractController
{
    public function __construct(private ManagerRegistry $doctrine2)
    {
    }

    #[Route('/', name: 'entetepiece.list')]
    public function index(Request $request, EntetepieceRepository $entetepieceRepository, ManagerRegistry $doctrine): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchPiece();
        $searchForm = $this->createForm(SearchPieceFormType::class, $searchData);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $entetepieceRepository->findPaginated($searchActive, $page);
        $invoiceIds = array_map(static fn (Entetepiece $piece): int => $piece->getId(), $pagination['items']);
        $remiseByInvoice = $entetepieceRepository->getWeightedRemiseByInvoiceIds($invoiceIds);
        $amountByInvoice = $entetepieceRepository->getTotalAmountByInvoiceIds($invoiceIds);
        $lineCountByInvoice = $entetepieceRepository->getLineCountByInvoiceIds($invoiceIds);

        return $this->render('entetepiece/index.html.twig', [
            'search' => $searchForm->createView(),
            'entetepieces' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
            'remiseByInvoice' => $remiseByInvoice,
            'amountByInvoice' => $amountByInvoice,
            'lineCountByInvoice' => $lineCountByInvoice,
        ]);
    }

    #[Route('/edit/{id?0}', name: 'entetepiece.edit')]
    public function addEntetePiece(ManagerRegistry $doctrine, Request $request, int $id): Response
    {
        $repository = $doctrine->getRepository(Entetepiece::class);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $currentDossier = $user->getCurrentDossier();
        $entetepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        $repositoryLignes = $doctrine->getRepository(Lignepiece::class);
        $lignepieces = $repositoryLignes->findBy(['piece' => $id]);

        $new = false;
        if (!$entetepiece) {
            $entetepiece = new Entetepiece();
            $new = true;
            if ($currentDossier !== null) {
                $entetepiece->setDossier($currentDossier);
                if ($entetepiece->getDevise() === null) {
                    $entetepiece->setDevise($currentDossier->getDevise());
                }
            }
            if ($entetepiece->getDatep() === null) {
                $entetepiece->setDatep(new \DateTimeImmutable('today'));
            }
        }
        $originalType = $new ? null : $entetepiece->getType();

        $entetepiece->doctrine = $doctrine;
        $entetepiece->user = $this->getUser();

        $form = $this->createForm(EntetePieceFormType::class, $entetepiece);
        $form->remove('delai');
        $form->remove('edition');
        $form->remove('rapport');
        $form->remove('pieceno');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$new && $originalType !== null && $entetepiece->getType() !== $originalType) {
                $entetepiece->setType($originalType);
                $this->addFlash('warning', 'Le type de piece est verrouille apres creation.');
            }

            if ($currentDossier !== null) {
                $entetepiece->setDossier($currentDossier);
                if ($entetepiece->getDevise() === null) {
                    $entetepiece->setDevise($currentDossier->getDevise());
                }
            }
            if ($entetepiece->getDatep() === null) {
                $entetepiece->setDatep(new \DateTimeImmutable('today'));
            }
            if ($entetepiece->getReglement() === null && $entetepiece->getClient()?->getReglement() !== null) {
                $entetepiece->setReglement($entetepiece->getClient()->getReglement());
            }

            if ($new) {
                $message = "L'entete de piece est ajoutee avec succes";
                $nextPieceNo = $this->getAndIncrementDossierCounter($entetepiece->getType(), $currentDossier);
                $entetepiece->setPieceno($nextPieceNo);
            } else {
                $message = "L'entete de piece a ete mise a jour avec succes";
            }

            $entityManager = $doctrine->getManager();
            $entityManager->persist($entetepiece);
            $entityManager->flush();

            $this->addFlash('success', $message);
            if ($new) {
                $this->addFlash('warning', 'Pensez à ajouter au moins une ligne avant de générer la facture.');
            }
            $redirectUrl = $this->buildPieceLinesRedirectUrl((int) $entetepiece->getId());

            return $this->redirect($redirectUrl);
        }

        return $this->render('entetepiece/add-entetepiece.html.twig', [
            'entetepiece' => $form->createView(),
            'id' => $id,
            'lignepieces' => $lignepieces,
        ]);
    }

    #[Route('/delete/{id}', name: 'entetepiece.delete')]
    public function deleteEntetePiece(ManagerRegistry $doctrine, int $id): RedirectResponse
    {
        $repository = $doctrine->getRepository(Entetepiece::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $entetepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
        if ($entetepiece) {
            $manager = $doctrine->getManager();
            $manager->remove($entetepiece);
            $manager->flush();
            $this->addFlash('success', "L'entete de piece a ete supprimee avec succes");
        } else {
            $this->addFlash('error', "L'entete de piece demandee n'existe pas");
        }

        return $this->redirectToRoute('entetepiece.list');
    }

    #[Route('/ca/annee/', name: 'ca_annee')]
    public function getCaAnneeMois(Request $request, EntetepieceRepository $repositoryPiece): Response
    {
        $annee = 2025;
        $mois = 1;

        if ($annee > 0) {
            $caAnneeMois = $repositoryPiece->getCaAnneeMois($annee, $mois);
            $montant = $caAnneeMois[0]['mont'];

            return $this->json(['code' => 200, 'message' => $montant], 200);
        }

        return $this->json(['code' => 200, 'message' => 0], 200);
    }

    private function getAndIncrementDossierCounter(?string $pieceType, ?\App\Entity\Dossier $dossier): int
    {
        if ($dossier === null) {
            return 1;
        }

        $normalized = strtolower(trim((string) $pieceType));

        return match ($normalized) {
            'devis' => $this->incrementCounter(
                current: $dossier->getDevisno(),
                setter: static fn (int $value) => $dossier->setDevisno($value)
            ),
            'commande' => $this->incrementCounter(
                current: $dossier->getCmdno(),
                setter: static fn (int $value) => $dossier->setCmdno($value)
            ),
            'bl' => $this->incrementCounter(
                current: $dossier->getBlno(),
                setter: static fn (int $value) => $dossier->setBlno($value)
            ),
            default => $this->incrementCounter(
                current: $dossier->getFactureno(),
                setter: static fn (int $value) => $dossier->setFactureno($value)
            ),
        };
    }

    private function incrementCounter(?int $current, callable $setter): int
    {
        $next = ($current ?? 0) + 1;
        $setter($next);

        return $next;
    }

    private function buildPieceLinesRedirectUrl(int $pieceId): string
    {
        return $this->generateUrl('entetepiece.edit', [
            'id' => $pieceId,
            'scroll' => 'piece-lines',
        ]) . '#piece-lines';
    }
}

