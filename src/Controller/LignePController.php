<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Clients;
use App\Entity\Entetepiece;
use App\Entity\Lignepiece;
use App\Entity\Tarifvente;
use App\Entity\User;
use App\Enum\SensEnum;
use App\Form\LignepieceFormType;
use App\Service\CodeOperationService;
use App\Service\StockMovementService;
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
    public function __construct(
        private readonly CodeOperationService $codeOperationService,
        private readonly StockMovementService $stockMovementService,
    ) {
    }

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
        $origin = $this->resolvePieceOriginToken((string) $request->query->get('origin', ''));

        $repository = $doctrine->getRepository(Lignepiece::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $lignepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        if ($lignepiece instanceof Lignepiece && $this->isPieceReadOnly($lignepiece->getPiece())) {
            $this->addFlash('warning', 'Cette pièce est périmée et ses lignes sont en lecture seule.');

            return $this->redirect($this->buildPieceRedirectUrl((int) ($lignepiece->getPiece()?->getId() ?? $pceId), $origin));
        }
        if ($lignepiece instanceof Lignepiece && $this->isInternalPiece($lignepiece->getPiece()) && !$this->canManageInternalPieces()) {
            $this->addFlash('error', 'La gestion des pieces internes est reservee aux roles Admin ou Comptable.');

            return $this->redirect($this->buildPieceRedirectUrl((int) ($lignepiece->getPiece()?->getId() ?? $pceId), $origin));
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

            $lignepiece->setSens($this->codeOperationService->resolveLineSensFromPiece($lignepiece->getPiece()));
            $lignepiece->setMontant($this->computeMontant($lignepiece));
            $entityManager = $doctrine->getManager();
            $entityManager->persist($lignepiece);
            $entityManager->flush();
            $stockWarning = $this->applyStockMovementIfNeeded($lignepiece);
            $this->recalculatePieceAmount($entityManager, $lignepiece->getPiece());
            $this->markPieceValideeAfterCompleteLine($lignepiece->getPiece());
            $entityManager->flush();

            $this->addFlash('success', $message);
            if ($stockWarning !== null) {
                $this->addFlash('warning', $stockWarning);
            }

            return $this->redirect($this->buildPieceRedirectUrl($pceId, $origin));
        }

        return $this->render('lignepiece/add-lignepiece.html.twig', [
            'lignepiece' => $form->createView(),
            'id' => $id,
            'pceId' => $pceId,
            'origin' => $origin,
            'pieceTierId' => $lignepiece->getPiece()?->getTierId() ?? 0,
            'pieceTierType' => (string) ($lignepiece->getPiece()?->getTypet() ?? ''),
        ]);
    }

    #[Route('/add/{pceId?0}', name: 'lignepiece.add')]
    public function addLignepiece(ManagerRegistry $doctrine, Request $request, int $pceId): Response
    {
        $origin = $this->resolvePieceOriginToken((string) $request->query->get('origin', ''));
        $backRoute = $this->resolvePieceListRoute($origin);

        $repository = $doctrine->getRepository(Entetepiece::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $entetePiece = $repository->findOneBy(['id' => $pceId, 'dossier' => $currentDossier]);
        if ($entetePiece === null) {
            $this->addFlash('error', "La piece demandee n'existe pas");

            return $this->redirectToRoute($backRoute);
        }

        if ($this->isPieceReadOnly($entetePiece)) {
            $this->addFlash('warning', 'Cette pièce est périmée et ses lignes sont en lecture seule.');

            return $this->redirect($this->buildPieceRedirectUrl((int) $entetePiece->getId(), $origin));
        }
        if ($this->isInternalPiece($entetePiece) && !$this->canManageInternalPieces()) {
            $this->addFlash('error', 'La gestion des pieces internes est reservee aux roles Admin ou Comptable.');

            return $this->redirectToRoute($backRoute);
        }

        $lignepiece = new Lignepiece();
        $lignepiece->doctrine = $doctrine;
        $lignepiece->user = $this->getUser();
        $lignepiece->setPiece($entetePiece);
        $lignepiece->setDossier($entetePiece->getDossier());

        $form = $this->createForm(LignepieceFormType::class, $lignepiece);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lignepiece->setSens($this->codeOperationService->resolveLineSensFromPiece($entetePiece));
            $lignepiece->setMontant($this->computeMontant($lignepiece));

            $entityManager = $doctrine->getManager();
            $entityManager->persist($lignepiece);
            $entityManager->flush();
            $stockWarning = $this->applyStockMovementIfNeeded($lignepiece);
            $this->recalculatePieceAmount($entityManager, $entetePiece);
            $this->markPieceValideeAfterCompleteLine($entetePiece);
            $entityManager->flush();

            $this->addFlash('success', 'La ligne piece est ajoutee avec succes');
            if ($stockWarning !== null) {
                $this->addFlash('warning', $stockWarning);
            }

            return $this->redirect($this->buildPieceRedirectUrl($pceId, $origin));
        }

        return $this->render('lignepiece/add-lignepiece.html.twig', [
            'lignepiece' => $form->createView(),
            'id' => 0,
            'pceId' => $pceId,
            'origin' => $origin,
            'pieceTierId' => $entetePiece->getTierId() ?? 0,
            'pieceTierType' => (string) ($entetePiece->getTypet() ?? ''),
        ]);
    }

    #[Route('/api/articles', name: 'lignepiece.api_articles', methods: ['GET'])]
    public function listArticlesForAjax(ManagerRegistry $doctrine): JsonResponse
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return $this->json(['success' => false, 'message' => 'Dossier introuvable.'], 403);
        }

        $articles = $doctrine->getRepository(Article::class)->createQueryBuilder('a')
            ->where('a.dossier = :dossier')
            ->setParameter('dossier', $currentDossier)
            ->orderBy('a.libelle', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'items' => array_map(static fn (Article $article): array => [
                'id' => (int) $article->getId(),
                'libelle' => (string) $article->getLibelle(),
            ], $articles),
        ]);
    }

    #[Route('/api/piece/{pceId<\d+>}/lignes', name: 'lignepiece.api_list', methods: ['GET'])]
    public function listPieceLignesForAjax(ManagerRegistry $doctrine, int $pceId): JsonResponse
    {
        $piece = $this->findCurrentDossierPiece($doctrine, $pceId);
        if (!$piece instanceof Entetepiece) {
            return $this->json(['success' => false, 'message' => "La piece demandee n'existe pas."], 404);
        }

        $lignes = $doctrine->getRepository(Lignepiece::class)->findBy(['piece' => $piece], ['id' => 'ASC']);

        return $this->json([
            'success' => true,
            'items' => array_map(fn (Lignepiece $ligne): array => $this->serializeLigne($ligne), $lignes),
            'piece' => $this->serializePieceLineSummary($piece, count($lignes)),
        ]);
    }

    #[Route('/api/piece/{pceId<\d+>}/lignes', name: 'lignepiece.api_create', methods: ['POST'])]
    public function createLigneForAjax(ManagerRegistry $doctrine, Request $request, int $pceId): JsonResponse
    {
        $piece = $this->findCurrentDossierPiece($doctrine, $pceId);
        if (!$piece instanceof Entetepiece) {
            return $this->json(['success' => false, 'message' => "La piece demandee n'existe pas."], 404);
        }
        if ($this->isPieceReadOnly($piece)) {
            return $this->json(['success' => false, 'message' => 'Cette piece est en lecture seule.'], 403);
        }
        if ($this->isInternalPiece($piece) && !$this->canManageInternalPieces()) {
            return $this->json(['success' => false, 'message' => 'La gestion des pieces internes est reservee aux roles Admin ou Comptable.'], 403);
        }

        $payload = $this->getAjaxPayload($request);
        $article = $this->resolveArticleFromPayload($doctrine, $payload);
        if (!$article instanceof Article) {
            return $this->json(['success' => false, 'message' => 'Article obligatoire ou introuvable.'], 422);
        }

        $values = $this->validateLignePayload($payload, true);
        if (isset($values['message'])) {
            return $this->json(['success' => false, 'message' => $values['message']], 422);
        }

        $lignepiece = new Lignepiece();
        $lignepiece->doctrine = $doctrine;
        $lignepiece->user = $this->getUser();
        $lignepiece->setPiece($piece);
        $lignepiece->setDossier($piece->getDossier());
        $lignepiece->setArticle($article);
        $lignepiece->setDesignation($article->getLibelle());
        $lignepiece->setQte($values['qte']);
        $lignepiece->setPub($values['pub']);
        $lignepiece->setRemise($values['remise']);
        $lignepiece->setSens($this->codeOperationService->resolveLineSensFromPiece($piece));
        $lignepiece->setMontant($this->computeMontant($lignepiece));

        $manager = $doctrine->getManager();
        $manager->persist($lignepiece);
        $manager->flush();
        $stockWarning = $this->applyStockMovementIfNeeded($lignepiece);
        $this->recalculatePieceAmount($manager, $piece);
        $this->markPieceValideeAfterCompleteLine($piece);
        $manager->flush();

        $lineCount = (int) $doctrine->getRepository(Lignepiece::class)->count(['piece' => $piece]);

        return $this->json([
            'success' => true,
            'message' => 'Ligne ajoutee.',
            'warning' => $stockWarning,
            'item' => $this->serializeLigne($lignepiece),
            'piece' => $this->serializePieceLineSummary($piece, $lineCount),
        ], 201);
    }

    #[Route('/api/lignes/{id<\d+>}', name: 'lignepiece.api_update', methods: ['POST'])]
    public function updateLigneForAjax(ManagerRegistry $doctrine, Request $request, int $id): JsonResponse
    {
        $lignepiece = $this->findCurrentDossierLigne($doctrine, $id);
        if (!$lignepiece instanceof Lignepiece) {
            return $this->json(['success' => false, 'message' => "La ligne demandee n'existe pas."], 404);
        }

        $piece = $lignepiece->getPiece();
        if ($this->isPieceReadOnly($piece)) {
            return $this->json(['success' => false, 'message' => 'Cette piece est en lecture seule.'], 403);
        }
        if ($this->isInternalPiece($piece) && !$this->canManageInternalPieces()) {
            return $this->json(['success' => false, 'message' => 'La gestion des pieces internes est reservee aux roles Admin ou Comptable.'], 403);
        }

        $payload = $this->getAjaxPayload($request);
        $article = $this->resolveArticleFromPayload($doctrine, $payload);
        if ($article instanceof Article) {
            $lignepiece->setArticle($article);
            $lignepiece->setDesignation($article->getLibelle());
        }

        $values = $this->validateLignePayload($payload, false);
        if (isset($values['message'])) {
            return $this->json(['success' => false, 'message' => $values['message']], 422);
        }

        $lignepiece->setQte($values['qte']);
        $lignepiece->setPub($values['pub']);
        $lignepiece->setRemise($values['remise']);
        $lignepiece->setMontant($this->computeMontant($lignepiece));

        $manager = $doctrine->getManager();
        $manager->persist($lignepiece);
        $manager->flush();
        $stockWarning = $this->applyStockMovementIfNeeded($lignepiece);
        $this->recalculatePieceAmount($manager, $piece);
        $this->markPieceValideeAfterCompleteLine($piece);
        $manager->flush();

        $lineCount = $piece instanceof Entetepiece
            ? (int) $doctrine->getRepository(Lignepiece::class)->count(['piece' => $piece])
            : 0;

        return $this->json([
            'success' => true,
            'message' => 'Ligne mise a jour.',
            'warning' => $stockWarning,
            'item' => $this->serializeLigne($lignepiece),
            'piece' => $piece instanceof Entetepiece ? $this->serializePieceLineSummary($piece, $lineCount) : null,
        ]);
    }

    #[Route('/api/lignes/{id<\d+>}/delete', name: 'lignepiece.api_delete', methods: ['POST', 'DELETE'])]
    public function deleteLigneForAjax(ManagerRegistry $doctrine, int $id): JsonResponse
    {
        $lignepiece = $this->findCurrentDossierLigne($doctrine, $id);
        if (!$lignepiece instanceof Lignepiece) {
            return $this->json(['success' => false, 'message' => "La ligne demandee n'existe pas."], 404);
        }

        $piece = $lignepiece->getPiece();
        if ($this->isPieceReadOnly($piece)) {
            return $this->json(['success' => false, 'message' => 'Cette piece est en lecture seule.'], 403);
        }
        if ($this->isInternalPiece($piece) && !$this->canManageInternalPieces()) {
            return $this->json(['success' => false, 'message' => 'La gestion des pieces internes est reservee aux roles Admin ou Comptable.'], 403);
        }

        $manager = $doctrine->getManager();
        $manager->remove($lignepiece);
        $manager->flush();

        $lineCount = 0;
        if ($piece instanceof Entetepiece) {
            $this->recalculatePieceAmount($manager, $piece);
            $manager->flush();
            $lineCount = (int) $doctrine->getRepository(Lignepiece::class)->count(['piece' => $piece]);
        }

        return $this->json([
            'success' => true,
            'message' => 'Ligne supprimee.',
            'piece' => $piece instanceof Entetepiece ? $this->serializePieceLineSummary($piece, $lineCount) : null,
        ]);
    }

    #[Route('/delete/{id}', name: 'lignepiece.delete')]
    public function deleteLignepiece(ManagerRegistry $doctrine, Request $request, int $id): RedirectResponse
    {
        $origin = $this->resolvePieceOriginToken((string) $request->query->get('origin', ''));
        $backRoute = $this->resolvePieceListRoute($origin);

        $repository = $doctrine->getRepository(Lignepiece::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $lignepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        if ($lignepiece) {
            $pieceId = $lignepiece->getPiece()?->getId();
            if ($this->isInternalPiece($lignepiece->getPiece()) && !$this->canManageInternalPieces()) {
                $this->addFlash('error', 'La gestion des pieces internes est reservee aux roles Admin ou Comptable.');

                if ($pieceId !== null) {
                    return $this->redirect($this->buildPieceRedirectUrl($pieceId, $origin));
                }

                return $this->redirectToRoute($backRoute);
            }
            if ($this->isPieceReadOnly($lignepiece->getPiece())) {
                $this->addFlash('warning', 'Cette pièce est périmée et ses lignes sont en lecture seule.');

                if ($pieceId !== null) {
                    return $this->redirect($this->buildPieceRedirectUrl($pieceId, $origin));
                }

                return $this->redirectToRoute($backRoute);
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
                return $this->redirect($this->buildPieceRedirectUrl($pieceId, $origin));
            }
        } else {
            $this->addFlash('error', "La ligne piece demandee n'existe pas");
        }

        $fallbackPieceId = $request->query->getInt('pceId', 0);
        if ($fallbackPieceId > 0) {
            return $this->redirect($this->buildPieceRedirectUrl($fallbackPieceId, $origin));
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

    /**
     * @return array<string, mixed>
     */
    private function getAjaxPayload(Request $request): array
    {
        $payload = [];
        if (str_contains((string) $request->headers->get('Content-Type', ''), 'application/json')) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return array_merge($payload, $request->request->all());
    }

    private function findCurrentDossierPiece(ManagerRegistry $doctrine, int $pieceId): ?Entetepiece
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return null;
        }

        $piece = $doctrine->getRepository(Entetepiece::class)->findOneBy([
            'id' => $pieceId,
            'dossier' => $currentDossier,
        ]);

        return $piece instanceof Entetepiece ? $piece : null;
    }

    private function findCurrentDossierLigne(ManagerRegistry $doctrine, int $ligneId): ?Lignepiece
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return null;
        }

        $lignepiece = $doctrine->getRepository(Lignepiece::class)->findOneBy([
            'id' => $ligneId,
            'dossier' => $currentDossier,
        ]);

        return $lignepiece instanceof Lignepiece ? $lignepiece : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveArticleFromPayload(ManagerRegistry $doctrine, array $payload): ?Article
    {
        $articleRaw = trim((string) ($payload['articleId'] ?? $payload['article'] ?? ''));
        if ($articleRaw === '' || !ctype_digit($articleRaw)) {
            return null;
        }

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return null;
        }

        $article = $doctrine->getRepository(Article::class)->findOneBy([
            'id' => (int) $articleRaw,
            'dossier' => $currentDossier,
        ]);

        return $article instanceof Article ? $article : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{qte?: float, pub?: float, remise?: float, message?: string}
     */
    private function validateLignePayload(array $payload, bool $requirePub): array
    {
        $qteRaw = trim((string) ($payload['qte'] ?? $payload['qty'] ?? ''));
        $pubRaw = trim((string) ($payload['pub'] ?? $payload['price'] ?? ''));
        $remiseRaw = trim((string) ($payload['remise'] ?? '0'));

        if ($qteRaw === '' || !is_numeric($qteRaw) || (float) $qteRaw <= 0) {
            return ['message' => 'La quantite doit etre superieure a 0.'];
        }

        if ($pubRaw === '') {
            if ($requirePub) {
                return ['message' => 'Le prix unitaire est obligatoire.'];
            }
            $pubRaw = '0';
        }
        if (!is_numeric($pubRaw) || (float) $pubRaw < 0) {
            return ['message' => 'Le prix unitaire doit etre positif.'];
        }
        if ($requirePub && (float) $pubRaw <= 0) {
            return ['message' => 'Le prix unitaire doit etre superieur a 0.'];
        }

        if ($remiseRaw === '') {
            $remiseRaw = '0';
        }
        if (!is_numeric($remiseRaw) || (float) $remiseRaw < 0 || (float) $remiseRaw > 100) {
            return ['message' => 'La remise doit etre comprise entre 0 et 100.'];
        }

        return [
            'qte' => round((float) $qteRaw, 4),
            'pub' => round((float) $pubRaw, 4),
            'remise' => round((float) $remiseRaw, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLigne(Lignepiece $lignepiece): array
    {
        $article = $lignepiece->getArticle();
        $qte = (float) ($lignepiece->getQte() ?? 0);
        $pub = (float) ($lignepiece->getPub() ?? 0);
        $remise = (float) ($lignepiece->getRemise() ?? 0);
        $montant = (float) ($lignepiece->getMontant() ?? $this->computeMontant($lignepiece));

        return [
            'id' => (int) ($lignepiece->getId() ?? 0),
            'articleId' => $article?->getId(),
            'articleText' => (string) ($lignepiece->getDesignation() ?? $article?->getLibelle() ?? ''),
            'article' => (string) ($lignepiece->getDesignation() ?? $article?->getLibelle() ?? ''),
            'qte' => $qte,
            'qty' => $qte,
            'pub' => $pub,
            'remise' => $remise,
            'montant' => $montant,
            'qteSt' => $lignepiece->getQteSt(),
            'sens' => $lignepiece->getSens()?->label(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePieceLineSummary(Entetepiece $piece, int $lineCount): array
    {
        $montant = (float) ($piece->getMontant() ?? 0);

        return [
            'id' => (int) ($piece->getId() ?? 0),
            'lignes' => $lineCount,
            'montant' => $montant,
            'montantDisplay' => number_format($montant, 2, ',', ' '),
            'statut' => (string) ($piece->getStatut() ?? ''),
            'statutLabel' => $this->getPieceStatusDisplayLabel($piece->getStatut()),
        ];
    }

    private function markPieceValideeAfterCompleteLine(?Entetepiece $piece): void
    {
        if (!$piece instanceof Entetepiece || $this->isPieceReadOnly($piece)) {
            return;
        }

        $piece->setStatut('Validee');
    }

    private function getPieceStatusDisplayLabel(?string $status): string
    {
        $normalized = $this->normalizeToken($status);

        return match ($normalized) {
            'brouillon' => 'Brouillon',
            'active' => 'Active',
            'validee', 'valide' => 'Validee',
            'perimee', 'perime', 'archivee', 'archive' => 'Perimee',
            default => trim((string) $status) !== '' ? (string) $status : '-',
        };
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

    private function applyStockMovementIfNeeded(Lignepiece $lignepiece): ?string
    {
        $piece = $lignepiece->getPiece();
        if (!$piece instanceof Entetepiece) {
            return null;
        }

        if (!in_array($piece->getType(), ['BL', 'Facture'], true)) {
            return null;
        }

        if ($piece->getCodeOperation()?->getSens() !== SensEnum::CREDIT) {
            return null;
        }

        if ($lignepiece->getQteSt() !== null && $lignepiece->getMouvementDeStock() !== null) {
            return null;
        }

        try {
            $this->stockMovementService->consumeStock($lignepiece);
        } catch (\Throwable $e) {
            return 'La ligne est enregistree, mais le mouvement de stock n a pas pu etre applique: ' . $e->getMessage();
        }

        return null;
    }

    private function buildPieceRedirectUrl(int $pieceId, ?string $origin = null): string
    {
        return $this->generateUrl('entetepiece.edit', array_merge([
            'id' => $pieceId,
            'scroll' => 'piece-lines',
        ], $this->buildPieceOriginQueryParams($origin))) . '#piece-lines';
    }

    private function resolvePieceOriginToken(?string $origin): ?string
    {
        return match ($this->normalizeToken($origin)) {
            'fournisseur' => 'fournisseur',
            'interne', 'tierinterne', 'tiersinterne' => 'interne',
            'client', 'prospect' => 'client',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function buildPieceOriginQueryParams(?string $origin): array
    {
        return $origin !== null ? ['origin' => $origin] : [];
    }

    private function resolvePieceListRoute(?string $origin): string
    {
        return match ($origin) {
            'fournisseur' => 'entetepiece.fournisseur_list',
            'interne' => 'entetepiece.interne_list',
            default => 'entetepiece.client_list',
        };
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

    private function canManageInternalPieces(): bool
    {
        return $this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_COMPTABLE');
    }

    private function isInternalPiece(?Entetepiece $piece): bool
    {
        if (!$piece instanceof Entetepiece) {
            return false;
        }

        return in_array($this->normalizeToken($piece->getTypet()), ['interne', 'tiersinterne', 'tierinterne'], true);
    }
}

