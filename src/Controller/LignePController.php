<?php

namespace App\Controller;

use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\Tarifvente;
use App\Entity\User;
use App\Form\LignepieceFormType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('lignepiece')]
class LignePController extends AbstractController
{
    private function computeMontant(Lignepiece $lignepiece): float
    {
        $qte = (float) ($lignepiece->getQte() ?? 0.0);
        $pub = (float) ($lignepiece->getPub() ?? 0.0);
        $remise = (float) ($lignepiece->getRemise() ?? 0.0);

        $montant = $qte * $pub * (1 - $remise / 100);
        if (!is_finite($montant)) {
            $montant = 0.0;
        }

        return round($montant, 2);
    }

    #[Route('/', name: 'lignepiece.list')]
    public function index(ManagerRegistry $doctrine): Response
    {
        $repository = $doctrine->getRepository(Lignepiece::class);
        $user = $this->getUser();
        if ($user instanceof User && $user->getCurrentDossier() !== null) {
            $lignepieces = $repository->findBy(['dossier' => $user->getCurrentDossier()]);
        } else {
            $lignepieces = [];
        }

        return $this->render('lignepiece/index.html.twig', [
            'lignepieces' => $lignepieces,
        ]);
    }

    #[Route('/edit/{id?0}/{pceId?0}', name: 'lignepiece.edit')]
    public function updateLignepiece(ManagerRegistry $doctrine, Request $request, int $id, int $pceId): Response
    {
        $repository = $doctrine->getRepository(Lignepiece::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $lignepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        $new = false;
        if (!$lignepiece) {
            $lignepiece = new Lignepiece();
            $new = true;
        }

        $lignepiece->doctrine = $doctrine;
        $lignepiece->user = $this->getUser();

        $form = $this->createForm(LignepieceFormType::class, $lignepiece);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message = $new
                ? 'La ligne piece est ajoutee avec succes'
                : 'La ligne piece a ete mise a jour avec succes';

            if ($lignepiece->getDossier() === null && $lignepiece->getPiece() !== null) {
                $lignepiece->setDossier($lignepiece->getPiece()->getDossier());
            }

            $lignepiece->setMontant($this->computeMontant($lignepiece));
            $entityManager = $doctrine->getManager();
            $entityManager->persist($lignepiece);
            $this->recalculatePieceAmount($entityManager, $lignepiece->getPiece());
            $entityManager->flush();

            $this->addFlash('success', $message);

            return $this->redirect($this->buildPieceRedirectUrl($pceId));
        }

        return $this->render('lignepiece/add-lignepiece.html.twig', [
            'lignepiece' => $form->createView(),
            'id' => $id,
            'pceId' => $pceId,
        ]);
    }

    #[Route('/add/{pceId?0}', name: 'lignepiece.add')]
    public function addLignepiece(ManagerRegistry $doctrine, Request $request, int $pceId): Response
    {
        $repository = $doctrine->getRepository(Entetepiece::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $entetePiece = $repository->findOneBy(['id' => $pceId, 'dossier' => $currentDossier]);
        if ($entetePiece === null) {
            $this->addFlash('error', "La piece demandee n'existe pas");

            return $this->redirectToRoute('entetepiece.list');
        }

        $lignepiece = new Lignepiece();
        $lignepiece->doctrine = $doctrine;
        $lignepiece->user = $this->getUser();
        $lignepiece->setPiece($entetePiece);
        $lignepiece->setDossier($entetePiece->getDossier());

        $form = $this->createForm(LignepieceFormType::class, $lignepiece);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lignepiece->setMontant($this->computeMontant($lignepiece));

            $entityManager = $doctrine->getManager();
            $entityManager->persist($lignepiece);
            $this->recalculatePieceAmount($entityManager, $entetePiece);
            $entityManager->flush();

            $this->addFlash('success', 'La ligne piece est ajoutee avec succes');

            return $this->redirect($this->buildPieceRedirectUrl($pceId));
        }

        return $this->render('lignepiece/add-lignepiece.html.twig', [
            'lignepiece' => $form->createView(),
            'id' => 0,
            'pceId' => $pceId,
        ]);
    }

    #[Route('/delete/{id}', name: 'lignepiece.delete')]
    public function deleteLignepiece(ManagerRegistry $doctrine, Request $request, int $id): RedirectResponse
    {
        $repository = $doctrine->getRepository(Lignepiece::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $lignepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        if ($lignepiece) {
            $pieceId = $lignepiece->getPiece()?->getId();
            $manager = $doctrine->getManager();
            $manager->remove($lignepiece);
            if ($pieceId !== null) {
                $piece = $doctrine->getRepository(Entetepiece::class)->find($pieceId);
                if ($piece instanceof Entetepiece) {
                    $this->recalculatePieceAmount($manager, $piece);
                }
            }
            $manager->flush();
            $this->addFlash('success', 'La ligne piece a ete supprimee avec succes');

            if ($pieceId !== null) {
                return $this->redirect($this->buildPieceRedirectUrl($pieceId));
            }
        } else {
            $this->addFlash('error', "La ligne piece demandee n'existe pas");
        }

        $fallbackPieceId = $request->query->getInt('pceId', 0);
        if ($fallbackPieceId > 0) {
            return $this->redirect($this->buildPieceRedirectUrl($fallbackPieceId));
        }

        return $this->redirectToRoute('lignepiece.list');
    }

    /**
     * Retourne le prix de vente d'un article en fonction du client de la piece en cours.
     */
    #[Route('/price', name: 'lignepiece.price', methods: ['GET'])]
    public function getTarifventePrice(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $articleId = $request->query->getInt('articleId', 0);
        $pieceId = $request->query->getInt('pieceId', 0);

        if ($articleId <= 0 || $pieceId <= 0) {
            return $this->json(['error' => 'Parametres manquants'], 400);
        }

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return $this->json(['error' => 'Dossier introuvable'], 403);
        }

        $piece = $doctrine->getRepository(Entetepiece::class)->find($pieceId);
        if ($piece === null || $piece->getDossier()?->getId() !== $currentDossier->getId()) {
            return $this->json(['error' => 'Piece introuvable'], 404);
        }

        $client = $piece->getClient();
        $tarifvente = $doctrine->getRepository(Tarifvente::class)->findOneBy([
            'article' => $articleId,
            'client' => $client,
            'dossier' => $currentDossier,
        ]);

        if (!$tarifvente) {
            return $this->json(['price' => null], 200);
        }

        return $this->json(['price' => $tarifvente->getPrix()], 200);
    }

    private function recalculatePieceAmount(\Doctrine\ORM\EntityManagerInterface $entityManager, ?Entetepiece $piece): void
    {
        if (!$piece instanceof Entetepiece) {
            return;
        }

        $sum = (float) $entityManager->createQueryBuilder()
            ->select('COALESCE(SUM(lp.montant), 0)')
            ->from(Lignepiece::class, 'lp')
            ->where('lp.piece = :piece')
            ->setParameter('piece', $piece)
            ->getQuery()
            ->getSingleScalarResult();

        $piece->setMontant(round($sum, 2));
        $entityManager->persist($piece);
    }

    private function buildPieceRedirectUrl(int $pieceId): string
    {
        return $this->generateUrl('entetepiece.edit', ['id' => $pieceId]) . '#piece-lines';
    }
}

