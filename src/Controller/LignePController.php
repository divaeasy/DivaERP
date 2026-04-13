<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Clients;
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

        if ($lignepiece instanceof Lignepiece && $this->isPieceReadOnly($lignepiece->getPiece())) {
            $this->addFlash('warning', 'Cette pièce est périmée et ses lignes sont en lecture seule.');

            return $this->redirect($this->buildPieceRedirectUrl((int) ($lignepiece->getPiece()?->getId() ?? $pceId)));
        }

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
            $entityManager->flush();
            $this->recalculatePieceAmount($entityManager, $lignepiece->getPiece());
            $entityManager->flush();

            $this->addFlash('success', $message);

            return $this->redirect($this->buildPieceRedirectUrl($pceId));
        }

        return $this->render('lignepiece/add-lignepiece.html.twig', [
            'lignepiece' => $form->createView(),
            'id' => $id,
            'pceId' => $pceId,
            'pieceTierId' => $lignepiece->getPiece()?->getTierId() ?? 0,
            'pieceTierType' => (string) ($lignepiece->getPiece()?->getTypet() ?? ''),
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

        if ($this->isPieceReadOnly($entetePiece)) {
            $this->addFlash('warning', 'Cette pièce est périmée et ses lignes sont en lecture seule.');

            return $this->redirect($this->buildPieceRedirectUrl((int) $entetePiece->getId()));
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
            $entityManager->flush();
            $this->recalculatePieceAmount($entityManager, $entetePiece);
            $entityManager->flush();

            $this->addFlash('success', 'La ligne piece est ajoutee avec succes');

            return $this->redirect($this->buildPieceRedirectUrl($pceId));
        }

        return $this->render('lignepiece/add-lignepiece.html.twig', [
            'lignepiece' => $form->createView(),
            'id' => 0,
            'pceId' => $pceId,
            'pieceTierId' => $entetePiece->getTierId() ?? 0,
            'pieceTierType' => (string) ($entetePiece->getTypet() ?? ''),
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
            if ($this->isPieceReadOnly($lignepiece->getPiece())) {
                $this->addFlash('warning', 'Cette pièce est périmée et ses lignes sont en lecture seule.');

                if ($pieceId !== null) {
                    return $this->redirect($this->buildPieceRedirectUrl($pieceId));
                }

                return $this->redirectToRoute('entetepiece.list');
            }
            $manager = $doctrine->getManager();
            $manager->remove($lignepiece);
            $manager->flush();
            if ($pieceId !== null) {
                $piece = $doctrine->getRepository(Entetepiece::class)->findOneBy([
                    'id' => $pieceId,
                    'dossier' => $currentDossier,
                ]);
                if ($piece instanceof Entetepiece) {
                    $this->recalculatePieceAmount($manager, $piece);
                    $manager->flush();
                }
            }
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
     * Retourne le prix de vente d'un article en fonction du tiers de la piece en cours.
     */
    #[Route('/price', name: 'lignepiece.price', methods: ['GET'])]
    public function getTarifventePrice(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        $articleId = $request->query->getInt('articleId', 0);
        $pieceId = $request->query->getInt('pieceId', 0);
        if ($pieceId <= 0) {
            $pieceId = $request->query->getInt('pceId', 0);
        }

        if ($articleId <= 0) {
            return $this->json(['error' => 'Parametres manquants'], 400);
        }

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return $this->json(['error' => 'Dossier introuvable'], 403);
        }

        $piece = null;
        if ($pieceId > 0) {
            $piece = $doctrine->getRepository(Entetepiece::class)->findOneBy([
                'id' => $pieceId,
                'dossier' => $currentDossier,
            ]);
        }

        $article = $doctrine->getRepository(Article::class)->find($articleId);
        if ($article === null || $article->getDossier()?->getId() !== $currentDossier->getId()) {
            return $this->json(['error' => 'Article introuvable'], 404);
        }

        $client = null;
        $pieceTierType = $this->normalizeToken($piece?->getTypet());
        $pieceTierId = (int) ($piece?->getTierId() ?? 0);

        if ($pieceTierType === 'client' && $pieceTierId > 0) {
            $client = $doctrine->getRepository(Clients::class)->findOneBy([
                'id' => $pieceTierId,
                'dossier' => $currentDossier,
            ]);
        }

        if ($client === null) {
            $requestTierType = $this->normalizeToken((string) $request->query->get('tierType', ''));
            $requestTierId = $request->query->getInt('tierId', 0);
            $requestClientId = $request->query->getInt('clientId', 0);

            if ($requestTierType === 'client' && $requestTierId > 0) {
                $client = $doctrine->getRepository(Clients::class)->findOneBy([
                    'id' => $requestTierId,
                    'dossier' => $currentDossier,
                ]);
            } elseif ($requestClientId > 0) {
                // Backward compatibility with old front-end payloads.
                $client = $doctrine->getRepository(Clients::class)->findOneBy([
                    'id' => $requestClientId,
                    'dossier' => $currentDossier,
                ]);
            }
        }
        $tarifventeRepository = $doctrine->getRepository(Tarifvente::class);

        if ($client !== null) {
            $clientTarifvente = $tarifventeRepository->createQueryBuilder('tv')
                ->where('tv.article = :article')
                ->andWhere('tv.client = :client')
                ->andWhere('tv.dossier = :dossier')
                ->setParameter('article', $article)
                ->setParameter('client', $client)
                ->setParameter('dossier', $currentDossier)
                ->orderBy('tv.dateeffet', 'DESC')
                ->addOrderBy('tv.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($clientTarifvente instanceof Tarifvente) {
                return $this->json(['price' => $clientTarifvente->getPrix()], 200);
            }

            $clientTarif = $client->getTarif();
            if ($clientTarif !== null) {
                $tarifBasedTarifvente = $tarifventeRepository->createQueryBuilder('tv')
                    ->where('tv.article = :article')
                    ->andWhere('tv.tarif = :tarif')
                    ->andWhere('tv.dossier = :dossier')
                    ->setParameter('article', $article)
                    ->setParameter('tarif', $clientTarif)
                    ->setParameter('dossier', $currentDossier)
                    ->orderBy('tv.dateeffet', 'DESC')
                    ->addOrderBy('tv.id', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($tarifBasedTarifvente instanceof Tarifvente) {
                    return $this->json(['price' => $tarifBasedTarifvente->getPrix()], 200);
                }
            }
        }

        $fallbackTarifvente = $tarifventeRepository->createQueryBuilder('tv')
            ->where('tv.article = :article')
            ->andWhere('tv.dossier = :dossier')
            ->setParameter('article', $article)
            ->setParameter('dossier', $currentDossier)
            ->orderBy('tv.dateeffet', 'DESC')
            ->addOrderBy('tv.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($fallbackTarifvente instanceof Tarifvente) {
            return $this->json(['price' => $fallbackTarifvente->getPrix()], 200);
        }

        if ($client !== null) {
            $lastUsedPriceForClient = $doctrine->getRepository(Lignepiece::class)
                ->createQueryBuilder('lp')
                ->select('lp.pub AS price')
                ->join('lp.piece', 'ep')
                ->where('lp.article = :article')
                ->andWhere('ep.tierId = :tierId')
                ->andWhere('ep.typet = :tierType')
                ->andWhere('lp.dossier = :dossier')
                ->andWhere('lp.pub IS NOT NULL')
                ->setParameter('article', $article)
                ->setParameter('tierId', (int) $client->getId())
                ->setParameter('tierType', 'Client')
                ->setParameter('dossier', $currentDossier)
                ->orderBy('lp.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (is_array($lastUsedPriceForClient) && array_key_exists('price', $lastUsedPriceForClient) && $lastUsedPriceForClient['price'] !== null) {
                return $this->json(['price' => (float) $lastUsedPriceForClient['price']], 200);
            }
        }

        $lastUsedPrice = $doctrine->getRepository(Lignepiece::class)->createQueryBuilder('lp')
            ->select('lp.pub AS price')
            ->join('lp.piece', 'ep')
            ->where('lp.article = :article')
            ->andWhere('lp.dossier = :dossier')
            ->andWhere('lp.pub IS NOT NULL')
            ->setParameter('article', $article)
            ->setParameter('dossier', $currentDossier)
            ->orderBy('lp.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (is_array($lastUsedPrice) && array_key_exists('price', $lastUsedPrice) && $lastUsedPrice['price'] !== null) {
            return $this->json(['price' => (float) $lastUsedPrice['price']], 200);
        }

        return $this->json(['price' => null], 200);
    }

    private function recalculatePieceAmount(\Doctrine\ORM\EntityManagerInterface $entityManager, ?Entetepiece $piece): void
    {
        if (!$piece instanceof Entetepiece) {
            return;
        }

        $sum = (float) $entityManager->createQueryBuilder()
            ->select('COALESCE(SUM((COALESCE(lp.qte, 0) * COALESCE(lp.pub, 0)) * (1 - (COALESCE(lp.remise, 0) / 100))), 0)')
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
        return $this->generateUrl('entetepiece.edit', [
            'id' => $pieceId,
            'scroll' => 'piece-lines',
        ]) . '#piece-lines';
    }

    private function isPieceReadOnly(?Entetepiece $piece): bool
    {
        if (!$piece instanceof Entetepiece) {
            return false;
        }

        return in_array($this->normalizeToken($piece->getStatut()), ['perimee', 'perime', 'archivee', 'archive'], true);
    }

    private function normalizeToken(?string $value): string
    {
        $normalized = mb_strtolower(trim((string) $value), 'UTF-8');
        $normalized = strtr($normalized, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ç' => 'c',
            'œ' => 'oe',
            'æ' => 'ae',
        ]);

        return (string) preg_replace('/[^a-z0-9]/', '', $normalized);
    }
}

