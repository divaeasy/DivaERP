<?php

namespace App\Controller;

use App\Entity\Clients;
use App\Entity\Devises;
use App\Entity\Dossier;
use App\Entity\Entetepiece;
use App\Entity\Fournisseur;
use App\Entity\Lignepiece;
use App\Entity\Prospects;
use App\Entity\Reglement;
use App\Entity\User;
use App\Form\EntetePieceFormType;
use App\Form\SearchPieceFormType;
use App\Model\SearchPiece;
use App\Repository\EntetepieceRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

#[Route('piece')]
class EntetePController extends AbstractController
{
    public function __construct(private ManagerRegistry $doctrine2)
    {
    }

    #[Route('/', name: 'entetepiece.list')]
    public function index(): Response
    {
        return $this->redirectToRoute('entetepiece.client_list');
    }

    #[Route('/client', name: 'entetepiece.client_list')]
    public function clientPieces(Request $request, EntetepieceRepository $entetepieceRepository): Response
    {
        return $this->renderPieceList(
            $request,
            $entetepieceRepository,
            ['Client', 'Prospect'],
            'entetepiece.client_list',
            'Client & Prospect'
        );
    }

    #[Route('/fournisseur', name: 'entetepiece.fournisseur_list')]
    public function fournisseurPieces(Request $request, EntetepieceRepository $entetepieceRepository): Response
    {
        return $this->renderPieceList(
            $request,
            $entetepieceRepository,
            'Fournisseur',
            'entetepiece.fournisseur_list',
            'Fournisseur'
        );
    }

    #[Route('/tiers/{tierType}', name: 'entetepiece.tier_autocomplete', methods: ['GET'])]
    public function tierAutocomplete(Request $request, string $tierType): JsonResponse
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return $this->json([]);
        }

        $tierClass = $this->resolveTierClass($tierType);
        if ($tierClass === null) {
            return $this->json([]);
        }

        $term = trim((string) $request->query->get('q', ''));
        $qb = $this->doctrine2->getRepository($tierClass)->createQueryBuilder('t')
            ->where('t.dossier = :dossier')
            ->setParameter('dossier', $currentDossier)
            ->orderBy('t.nom', 'ASC')
            ->setMaxResults(40);

        if ($term !== '') {
            $qb->andWhere('t.nom LIKE :term OR t.tel LIKE :term')
                ->setParameter('term', '%' . $term . '%');
        }

        $items = $qb->getQuery()->getResult();
        $payload = [];
        foreach ($items as $item) {
            $payload[] = [
                'id' => (int) $item->getId(),
                'name' => (string) ($item->getNom() ?? ''),
                'tel' => (string) ($item->getTel() ?? ''),
                'reglementId' => (int) ($item->getReglement()?->getId() ?? 0),
            ];
        }

        return $this->json($payload);
    }

    #[Route('/edit/{id?0}', name: 'entetepiece.edit')]
    public function addEntetePiece(ManagerRegistry $doctrine, Request $request, int $id): Response
    {
        $origin = $this->resolvePieceOriginToken((string) $request->query->get('origin', ''));
        $backRoute = $this->resolvePieceListRoute($origin);

        $repository = $doctrine->getRepository(Entetepiece::class);
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $currentDossier = $user->getCurrentDossier();
        $entetepiece = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);

        $repositoryLignes = $doctrine->getRepository(Lignepiece::class);
        $lignepieces = [];

        $new = false;
        if (!$entetepiece) {
            $entetepiece = new Entetepiece();
            $new = true;
            $id = 0;
            $entetepiece->setStatut('Brouillon');
            if ($origin === 'fournisseur') {
                $entetepiece->setTypet('Fournisseur');
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
        } else {
            $lignepieces = $repositoryLignes->findBy(['piece' => $entetepiece]);
        }

        $originalType = $new ? null : $entetepiece->getType();
        $originalStatus = $new ? 'Brouillon' : (string) ($entetepiece->getStatut() ?? 'Brouillon');
        $isReadOnly = !$new && $this->isPerimeeStatus($entetepiece->getStatut());
        $lineStats = $this->buildLineStats($lignepieces);

        if ($isReadOnly && $request->isMethod('POST')) {
            $this->addFlash('warning', 'Cette piece est perimee et ne peut plus etre modifiee.');

            return $this->redirectToRoute('entetepiece.edit', array_merge(
                ['id' => $entetepiece->getId()],
                $this->buildPieceOriginQueryParams($origin)
            ));
        }

        $entetepiece->doctrine = $doctrine;
        $entetepiece->user = $this->getUser();
        $entetepiece->setResolvedTierName($entetepiece->getTierName($doctrine));

        $form = $this->createForm(EntetePieceFormType::class, $entetepiece, [
            'read_only' => $isReadOnly,
            'tier_origin' => $origin,
        ]);
        $form->remove('delai');
        $form->remove('edition');
        $form->remove('rapport');
        $form->remove('pieceno');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($new && $origin === 'fournisseur') {
                $entetepiece->setTypet('Fournisseur');
            }

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

            $tier = $this->validateTierSelection($entetepiece, $form);
            if ($tier === false) {
                return $this->render('entetepiece/add-entetepiece.html.twig', [
                    'entetepiece' => $form->createView(),
                    'id' => $id,
                    'lignepieces' => $lignepieces,
                    'origin' => $origin,
                    'backRoute' => $backRoute,
                    'isReadOnly' => $isReadOnly,
                    'isPerimee' => $isReadOnly,
                    'statusProgression' => [
                        'lineCount' => $lineStats['lineCount'],
                        'invalidLineCount' => $lineStats['invalidLineCount'],
                        'originalStatus' => $originalStatus,
                    ],
                ]);
            }

            if ($entetepiece->getReglement() === null) {
                $tierReglement = $this->extractTierReglement($tier);
                if ($tierReglement instanceof Reglement) {
                    $entetepiece->setReglement($tierReglement);
                }
            }

            $requestedStatus = (string) ($entetepiece->getStatut() ?? 'Brouillon');
            $statusCheck = $this->validateStatusProgression(
                $originalStatus,
                $requestedStatus,
                $lineStats['lineCount'],
                $lineStats['invalidLineCount']
            );

            if (!$statusCheck['allowed']) {
                $form->get('statut')->addError(new FormError((string) $statusCheck['reason']));
                $this->addFlash('warning', (string) $statusCheck['reason']);
            } else {
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
                    $this->addFlash('warning', 'Pensez a ajouter au moins une ligne avant de generer la facture.');
                }

                return $this->redirectToRoute($backRoute);
            }
        }

        return $this->render('entetepiece/add-entetepiece.html.twig', [
            'entetepiece' => $form->createView(),
            'id' => $id,
            'lignepieces' => $lignepieces,
            'origin' => $origin,
            'backRoute' => $backRoute,
            'isReadOnly' => $isReadOnly,
            'isPerimee' => $isReadOnly,
            'statusProgression' => [
                'lineCount' => $lineStats['lineCount'],
                'invalidLineCount' => $lineStats['invalidLineCount'],
                'originalStatus' => $originalStatus,
            ],
        ]);
    }

    #[Route('/transition/{id}', name: 'entetepiece.transition', methods: ['POST'])]
    public function transitionPiece(ManagerRegistry $doctrine, Request $request, int $id): RedirectResponse
    {
        $origin = $this->resolvePieceOriginToken((string) $request->query->get('origin', ''));
        $backRoute = $this->resolvePieceListRoute($origin);

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $piece = $doctrine->getRepository(Entetepiece::class)->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        if (!$piece instanceof Entetepiece) {
            $this->addFlash('error', "La piece demandee n'existe pas.");

            return $this->redirectToRoute($backRoute);
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('transition_piece_' . $piece->getId(), $token)) {
            $this->addFlash('error', 'Demande de transition invalide.');

            return $this->redirectToRoute($backRoute);
        }

        $targetTypeRaw = (string) $request->request->get('targetType', '');
        $allowedTargets = $this->getTransitionTargetsForType($piece->getType());
        $targetType = $this->resolveAllowedTargetType($targetTypeRaw, $allowedTargets);
        if ($targetType === null) {
            $this->addFlash('warning', 'Type de transition non autorise pour cette piece.');

            return $this->redirectToRoute($backRoute);
        }

        $sourceLines = $doctrine->getRepository(Lignepiece::class)->findBy(['piece' => $piece]);
        $eligibility = $this->evaluateTransitionEligibility($piece, $sourceLines);
        if (!$eligibility['eligible']) {
            $this->addFlash('warning', (string) ($eligibility['reason'] ?? 'Cette piece ne peut pas etre convertie.'));

            return $this->redirectToRoute($backRoute);
        }

        $entityManager = $doctrine->getManager();

        $newPiece = new Entetepiece();
        $newPiece->doctrine = $doctrine;
        $newPiece->user = $user instanceof User ? $user : null;
        $newPiece->setType($targetType);
        $newPiece->setTypet($piece->getTypet() ?? 'Client');
        $newPiece->setTierId($piece->getTierId());
        $newPiece->setDossier($piece->getDossier());
        $newPiece->setDevise($piece->getDevise());
        $newPiece->setReglement($piece->getReglement());
        $newPiece->setRemise($piece->getRemise());
        $newPiece->setPieceref($piece->getPieceref());
        $newPiece->setDatep(new \DateTimeImmutable('today'));
        $newPiece->setStatut('Active');
        $newPiece->setPieceno($this->getAndIncrementDossierCounter($newPiece->getType(), $piece->getDossier()));

        $entityManager->persist($newPiece);

        $newAmount = 0.0;
        foreach ($sourceLines as $sourceLine) {
            $copiedLine = new Lignepiece();
            $copiedLine->doctrine = $doctrine;
            $copiedLine->user = $user instanceof User ? $user : null;
            $copiedLine->setPiece($newPiece);
            $copiedLine->setDossier($newPiece->getDossier());
            $copiedLine->setArticle($sourceLine->getArticle());
            $copiedLine->setDesignation($sourceLine->getDesignation());
            $copiedLine->setQte((float) ($sourceLine->getQte() ?? 0));
            $copiedLine->setPub((float) ($sourceLine->getPub() ?? 0));
            $copiedLine->setRemise($sourceLine->getRemise());
            $copiedLine->setMontant((float) ($sourceLine->getMontant() ?? 0));
            $newAmount += (float) ($sourceLine->getMontant() ?? 0);
            $entityManager->persist($copiedLine);
        }

        $newPiece->setMontant(round($newAmount, 2));
        $piece->setStatut('Perimee');
        $piece->doctrine = $doctrine;
        $piece->user = $user instanceof User ? $user : null;
        $entityManager->persist($piece);

        $entityManager->flush();

        $this->addFlash('success', sprintf(
            'Piece %s #%s creee. La piece source a ete archivee en statut Perimee.',
            $targetType,
            (string) ($newPiece->getPieceno() ?? '-')
        ));

        return $this->redirect($this->buildPieceLinesRedirectUrl((int) $newPiece->getId(), $origin));
    }

    #[Route('/create-minimal', name: 'entetepiece.create_minimal', methods: ['POST'])]
    public function createMinimal(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->isCsrfTokenValid('entetepiece_create_minimal', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de securite invalide.'], 403);
        }

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if (!$currentDossier instanceof Dossier) {
            return $this->json(['success' => false, 'message' => 'Aucun dossier courant selectionne.'], 403);
        }

        $pieceType = $this->resolveAllowedCreatePieceType((string) $request->request->get('type', 'Facture'));
        if ($pieceType === null) {
            return $this->json(['success' => false, 'message' => 'Type de piece invalide.'], 422);
        }

        $origin = $this->resolvePieceOriginToken((string) $request->request->get('origin', (string) $request->query->get('origin', '')));
        $allowedTierTypes = $this->resolveAllowedTierTypesForOrigin($origin);
        $tierType = $this->resolveAllowedTierType((string) $request->request->get('typet', ''), $allowedTierTypes);
        if ($tierType === null) {
            $tierType = $allowedTierTypes[0] ?? null;
        }
        if ($tierType === null) {
            return $this->json(['success' => false, 'message' => 'Type de tiers invalide.'], 422);
        }

        $tierIdRaw = trim((string) $request->request->get('tierId', ''));
        if ($tierIdRaw === '' || !ctype_digit($tierIdRaw)) {
            return $this->json(['success' => false, 'message' => 'Le tiers est obligatoire.'], 422);
        }

        $tierClass = $this->resolveTierClass($tierType);
        if ($tierClass === null) {
            return $this->json(['success' => false, 'message' => 'Type de tiers invalide.'], 422);
        }

        $tier = $doctrine->getRepository($tierClass)->findOneBy([
            'id' => (int) $tierIdRaw,
            'dossier' => $currentDossier,
        ]);
        if ($tier === null) {
            return $this->json(['success' => false, 'message' => 'Le tiers selectionne est introuvable.'], 422);
        }

        $pieceRef = trim((string) $request->request->get('pieceref', ''));
        $remiseRaw = trim((string) $request->request->get('remise', '0'));
        if ($remiseRaw !== '' && !is_numeric($remiseRaw)) {
            return $this->json(['success' => false, 'message' => 'La remise doit etre numerique.'], 422);
        }
        $remise = $remiseRaw === '' ? 0.0 : round((float) $remiseRaw, 2);
        if ($remise < 0) {
            return $this->json(['success' => false, 'message' => 'La remise ne peut pas etre negative.'], 422);
        }

        $status = $this->resolveEditableStatusValue((string) $request->request->get('statut', 'Brouillon'));
        if ($status === null) {
            return $this->json(['success' => false, 'message' => 'Le statut est invalide.'], 422);
        }

        $statusCheck = $this->validateStatusProgression(
            'Brouillon',
            $status,
            0,
            0
        );
        if (!$statusCheck['allowed']) {
            return $this->json(['success' => false, 'message' => (string) ($statusCheck['reason'] ?? 'Statut invalide.')], 422);
        }

        $dateRaw = trim((string) $request->request->get('datep', ''));
        if ($dateRaw !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRaw)) {
            return $this->json(['success' => false, 'message' => 'La date est invalide.'], 422);
        }
        $datep = $dateRaw !== ''
            ? \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw) ?: null
            : new \DateTimeImmutable('today');
        if (!$datep instanceof \DateTimeImmutable) {
            return $this->json(['success' => false, 'message' => 'La date est invalide.'], 422);
        }

        $devise = $currentDossier->getDevise();
        $deviseIdRaw = trim((string) $request->request->get('deviseId', ''));
        if ($deviseIdRaw !== '') {
            if (!ctype_digit($deviseIdRaw)) {
                return $this->json(['success' => false, 'message' => 'La devise est invalide.'], 422);
            }
            $resolvedDevise = $doctrine->getRepository(Devises::class)->find((int) $deviseIdRaw);
            if (!$resolvedDevise instanceof Devises) {
                return $this->json(['success' => false, 'message' => 'La devise selectionnee est introuvable.'], 422);
            }
            $devise = $resolvedDevise;
        }

        $reglement = null;
        $reglementIdRaw = trim((string) $request->request->get('reglementId', ''));
        if ($reglementIdRaw !== '') {
            if (!ctype_digit($reglementIdRaw)) {
                return $this->json(['success' => false, 'message' => 'Le reglement est invalide.'], 422);
            }
            $resolvedReglement = $doctrine->getRepository(Reglement::class)->find((int) $reglementIdRaw);
            if (!$resolvedReglement instanceof Reglement) {
                return $this->json(['success' => false, 'message' => 'Le reglement selectionne est introuvable.'], 422);
            }
            $reglement = $resolvedReglement;
        } else {
            $reglement = $this->extractTierReglement($tier);
        }

        $piece = new Entetepiece();
        $piece->setDossier($currentDossier);
        $piece->setType($pieceType);
        $piece->setTypet($tierType);
        $piece->setTierId((int) $tierIdRaw);
        $piece->setPieceno($this->getAndIncrementDossierCounter($pieceType, $currentDossier));
        $piece->setPieceref($pieceRef !== '' ? $pieceRef : null);
        $piece->setDevise($devise instanceof Devises ? $devise : null);
        $piece->setDatep($datep);
        $piece->setStatut($status);
        $piece->setRemise($remise);
        $piece->setReglement($reglement instanceof Reglement ? $reglement : null);
        $piece->setMontant(0.0);
        $piece->setResolvedTierName($this->extractTierName($tier));

        $manager = $doctrine->getManager();
        $manager->persist($piece);
        $manager->flush();

        $lineStats = ['lineCount' => 0, 'invalidLineCount' => 0];

        return $this->json([
            'success' => true,
            'message' => 'Piece creee avec succes.',
            'item' => $this->serializePieceListItem($piece, $doctrine, $origin, $lineStats),
        ]);
    }

    #[Route('/import', name: 'entetepiece.import', methods: ['POST'])]
    public function importPieces(Request $request, ManagerRegistry $doctrine): JsonResponse
    {
        if (!$this->isCsrfTokenValid('entetepiece_import', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de securite invalide.'], 403);
        }

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if (!$currentDossier instanceof Dossier) {
            return $this->json(['success' => false, 'message' => 'Aucun dossier courant selectionne.'], 403);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return $this->json(['success' => false, 'message' => 'Fichier invalide.'], 422);
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['csv', 'txt', 'xls', 'xlsx'], true)) {
            return $this->json(['success' => false, 'message' => 'Format non supporte. Utilisez CSV, XLS ou XLSX.'], 422);
        }

        try {
            $sheet = IOFactory::load((string) $file->getPathname())->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, true);
        } catch (\Throwable) {
            return $this->json(['success' => false, 'message' => 'Lecture du fichier impossible.'], 422);
        }

        if (count($rows) < 2) {
            return $this->json(['success' => false, 'message' => 'Le fichier ne contient aucune ligne a importer.'], 422);
        }

        $headerRow = array_shift($rows) ?: [];
        $headers = [];
        foreach ($headerRow as $col => $label) {
            $headers[(string) $col] = $this->normalizeToken((string) $label);
        }

        $origin = $this->resolvePieceOriginToken((string) $request->request->get('origin', (string) $request->query->get('origin', '')));
        $allowedTierTypes = $this->resolveAllowedTierTypesForOrigin($origin);
        $defaultTierType = $allowedTierTypes[0] ?? 'Client';

        $createdCount = 0;
        $errors = [];
        $manager = $doctrine->getManager();

        foreach ($rows as $index => $rawRow) {
            $lineNumber = $index + 2;
            $normalized = [];
            foreach ($rawRow as $col => $value) {
                $key = $headers[(string) $col] ?? '';
                if ($key === '') {
                    continue;
                }
                $normalized[$key] = is_string($value) ? trim($value) : $value;
            }

            $tierIdRaw = trim((string) ($normalized['tierid'] ?? $normalized['tiersid'] ?? ''));
            if ($tierIdRaw === '' || !ctype_digit($tierIdRaw)) {
                $errors[] = sprintf('Ligne %d: tierId manquant ou invalide.', $lineNumber);
                continue;
            }

            $pieceType = $this->resolveAllowedCreatePieceType((string) ($normalized['type'] ?? 'Facture'));
            if ($pieceType === null) {
                $errors[] = sprintf('Ligne %d: type de piece invalide.', $lineNumber);
                continue;
            }

            $tierType = $this->resolveAllowedTierType((string) ($normalized['typet'] ?? $defaultTierType), $allowedTierTypes);
            if ($tierType === null) {
                $errors[] = sprintf('Ligne %d: type de tiers invalide.', $lineNumber);
                continue;
            }

            $tierClass = $this->resolveTierClass($tierType);
            if ($tierClass === null) {
                $errors[] = sprintf('Ligne %d: type de tiers invalide.', $lineNumber);
                continue;
            }

            $tier = $doctrine->getRepository($tierClass)->findOneBy([
                'id' => (int) $tierIdRaw,
                'dossier' => $currentDossier,
            ]);
            if ($tier === null) {
                $errors[] = sprintf('Ligne %d: tiers introuvable dans le dossier.', $lineNumber);
                continue;
            }

            $status = $this->resolveEditableStatusValue((string) ($normalized['statut'] ?? 'Brouillon'));
            if ($status === null) {
                $errors[] = sprintf('Ligne %d: statut invalide.', $lineNumber);
                continue;
            }

            $remiseRaw = trim((string) ($normalized['remise'] ?? '0'));
            if ($remiseRaw !== '' && !is_numeric($remiseRaw)) {
                $errors[] = sprintf('Ligne %d: remise invalide.', $lineNumber);
                continue;
            }
            $remise = $remiseRaw === '' ? 0.0 : max(0.0, round((float) $remiseRaw, 2));

            $dateRaw = trim((string) ($normalized['datep'] ?? ''));
            $datep = new \DateTimeImmutable('today');
            if ($dateRaw !== '') {
                $parsedDate = \DateTimeImmutable::createFromFormat('Y-m-d', $dateRaw);
                if (!$parsedDate instanceof \DateTimeImmutable) {
                    $errors[] = sprintf('Ligne %d: date invalide (format attendu YYYY-MM-DD).', $lineNumber);
                    continue;
                }
                $datep = $parsedDate;
            }

            $piece = new Entetepiece();
            $piece->setDossier($currentDossier);
            $piece->setType($pieceType);
            $piece->setTypet($tierType);
            $piece->setTierId((int) $tierIdRaw);
            $piece->setPieceno($this->getAndIncrementDossierCounter($pieceType, $currentDossier));
            $piece->setPieceref(($normalized['pieceref'] ?? '') !== '' ? (string) $normalized['pieceref'] : null);
            $piece->setDevise($currentDossier->getDevise());
            $piece->setDatep($datep);
            $piece->setStatut($status);
            $piece->setRemise($remise);
            $piece->setReglement($this->extractTierReglement($tier));
            $piece->setMontant(0.0);
            $piece->setResolvedTierName($this->extractTierName($tier));

            $manager->persist($piece);
            ++$createdCount;
        }

        if ($createdCount === 0 && $errors !== []) {
            return $this->json([
                'success' => false,
                'message' => 'Aucune piece importee. ' . $errors[0],
                'errors' => $errors,
            ], 422);
        }

        $manager->flush();

        $message = sprintf('%d piece(s) importee(s).', $createdCount);
        if ($errors !== []) {
            $message .= sprintf(' %d ligne(s) ignoree(s).', count($errors));
        }

        return $this->json([
            'success' => true,
            'message' => $message,
            'createdCount' => $createdCount,
            'errorCount' => count($errors),
            'errors' => array_slice($errors, 0, 20),
        ]);
    }

    #[Route('/inline-update/{id<\d+>}', name: 'entetepiece.inline_update', methods: ['POST'])]
    public function inlineUpdate(Request $request, ManagerRegistry $doctrine, int $id): JsonResponse
    {
        if (!$this->isCsrfTokenValid('entetepiece_inline_update', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de securite invalide.'], 403);
        }

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if (!$currentDossier instanceof Dossier) {
            return $this->json(['success' => false, 'message' => 'Aucun dossier courant selectionne.'], 403);
        }

        $piece = $doctrine->getRepository(Entetepiece::class)->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);
        if (!$piece instanceof Entetepiece) {
            return $this->json(['success' => false, 'message' => 'Piece introuvable.'], 404);
        }

        if ($this->isPerimeeStatus($piece->getStatut())) {
            return $this->json(['success' => false, 'message' => 'Cette piece est perimee et ne peut plus etre modifiee.'], 422);
        }

        $origin = $this->resolvePieceOriginToken((string) $request->query->get('origin', (string) $request->request->get('origin', '')));
        $lineStats = $this->buildLineStats($doctrine->getRepository(Lignepiece::class)->findBy(['piece' => $piece]));

        $pieceTypeRaw = trim((string) $request->request->get('type', (string) ($piece->getType() ?? '')));
        $resolvedPieceType = $this->resolveAllowedCreatePieceType($pieceTypeRaw);
        if ($resolvedPieceType === null) {
            return $this->json(['success' => false, 'message' => 'Type de piece invalide.'], 422);
        }
        if ($this->normalizePieceType($resolvedPieceType) !== $this->normalizePieceType($piece->getType())) {
            if (($lineStats['lineCount'] ?? 0) > 0) {
                return $this->json(['success' => false, 'message' => 'Le type est verrouille quand la piece contient des lignes. Utilisez la conversion.'], 422);
            }
            $piece->setType($resolvedPieceType);
            $piece->setPieceno($this->getAndIncrementDossierCounter($resolvedPieceType, $currentDossier));
        }

        $allowedTierTypes = $this->resolveAllowedTierTypesForOrigin($origin);
        $tierType = $this->resolveAllowedTierType(
            (string) $request->request->get('typet', (string) $piece->getTypet()),
            $allowedTierTypes
        );
        if ($tierType === null) {
            return $this->json(['success' => false, 'message' => 'Type de tiers invalide.'], 422);
        }

        $tierIdRaw = trim((string) $request->request->get('tierId', (string) ($piece->getTierId() ?? '')));
        if ($tierIdRaw === '' || !ctype_digit($tierIdRaw)) {
            return $this->json(['success' => false, 'message' => 'Le tiers est obligatoire.'], 422);
        }

        $tierClass = $this->resolveTierClass($tierType);
        if ($tierClass === null) {
            return $this->json(['success' => false, 'message' => 'Type de tiers invalide.'], 422);
        }

        $tier = $doctrine->getRepository($tierClass)->findOneBy([
            'id' => (int) $tierIdRaw,
            'dossier' => $currentDossier,
        ]);
        if ($tier === null) {
            return $this->json(['success' => false, 'message' => 'Le tiers selectionne est introuvable.'], 422);
        }

        $pieceRef = trim((string) $request->request->get('pieceref', (string) ($piece->getPieceref() ?? '')));
        $remiseRaw = trim((string) $request->request->get('remise', (string) ($piece->getRemise() ?? '0')));
        if ($remiseRaw !== '' && !is_numeric($remiseRaw)) {
            return $this->json(['success' => false, 'message' => 'La remise doit etre numerique.'], 422);
        }
        $remise = $remiseRaw === '' ? 0.0 : round((float) $remiseRaw, 2);
        if ($remise < 0) {
            return $this->json(['success' => false, 'message' => 'La remise ne peut pas etre negative.'], 422);
        }

        $status = $this->resolveEditableStatusValue((string) $request->request->get('statut', (string) ($piece->getStatut() ?? '')));
        if ($status === null) {
            return $this->json(['success' => false, 'message' => 'Le statut est invalide.'], 422);
        }

        $statusCheck = $this->validateStatusProgression(
            (string) ($piece->getStatut() ?? 'Brouillon'),
            $status,
            (int) ($lineStats['lineCount'] ?? 0),
            (int) ($lineStats['invalidLineCount'] ?? 0)
        );
        if (!$statusCheck['allowed']) {
            return $this->json(['success' => false, 'message' => (string) ($statusCheck['reason'] ?? 'Statut invalide.')], 422);
        }

        $deviseIdRaw = trim((string) $request->request->get('deviseId', ''));
        if ($deviseIdRaw !== '') {
            if (!ctype_digit($deviseIdRaw)) {
                return $this->json(['success' => false, 'message' => 'La devise est invalide.'], 422);
            }
            $devise = $doctrine->getRepository(Devises::class)->find((int) $deviseIdRaw);
            if (!$devise instanceof Devises) {
                return $this->json(['success' => false, 'message' => 'La devise selectionnee est introuvable.'], 422);
            }
            $piece->setDevise($devise);
        }

        $yearRaw = trim((string) $request->request->get('annee', ''));
        if ($yearRaw !== '') {
            if (!ctype_digit($yearRaw)) {
                return $this->json(['success' => false, 'message' => 'L annee est invalide.'], 422);
            }
            $year = (int) $yearRaw;
            if ($year < 1900 || $year > 2100) {
                return $this->json(['success' => false, 'message' => 'L annee doit etre comprise entre 1900 et 2100.'], 422);
            }
            $baseDate = $piece->getDatep() instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromMutable(\DateTime::createFromInterface($piece->getDatep()))
                : new \DateTimeImmutable('today');
            $month = (int) $baseDate->format('m');
            $day = (int) $baseDate->format('d');
            $maxDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $safeDay = min($day, $maxDay);
            $piece->setDatep(new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $safeDay)));
        } elseif (!$piece->getDatep() instanceof \DateTimeInterface) {
            $piece->setDatep(new \DateTimeImmutable('today'));
        }

        if ($piece->getReglement() === null) {
            $tierReglement = $this->extractTierReglement($tier);
            if ($tierReglement instanceof Reglement) {
                $piece->setReglement($tierReglement);
            }
        }

        $piece->setTypet($tierType);
        $piece->setTierId((int) $tierIdRaw);
        $piece->setPieceref($pieceRef !== '' ? $pieceRef : null);
        $piece->setRemise($remise);
        $piece->setStatut($status);
        $piece->setResolvedTierName($this->extractTierName($tier));

        $doctrine->getManager()->flush();

        return $this->json([
            'success' => true,
            'message' => 'Piece mise a jour avec succes.',
            'item' => $this->serializePieceListItem($piece, $doctrine, $origin, $lineStats),
        ]);
    }

    #[Route('/delete/{id}', name: 'entetepiece.delete')]
    public function deleteEntetePiece(ManagerRegistry $doctrine, Request $request, int $id): RedirectResponse
    {
        $origin = $this->resolvePieceOriginToken((string) $request->query->get('origin', ''));
        $backRoute = $this->resolvePieceListRoute($origin);

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

        return $this->redirectToRoute($backRoute);
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

    private function renderPieceList(
        Request $request,
        EntetepieceRepository $entetepieceRepository,
        string|array|null $forcedTierType,
        string $listRoute,
        ?string $forcedTierLabel = null
    ): Response {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchPiece();
        $searchForm = $this->createForm(SearchPieceFormType::class, $searchData);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $entetepieceRepository->findPaginated($searchActive, $page, $forcedTierType);
        $this->hydrateTierNames($pagination['items']);

        $invoiceIds = array_map(static fn (Entetepiece $piece): int => (int) $piece->getId(), $pagination['items']);
        $remiseByInvoice = $entetepieceRepository->getWeightedRemiseByInvoiceIds($invoiceIds);
        $amountByInvoice = $entetepieceRepository->getTotalAmountByInvoiceIds($invoiceIds);
        $lineCountByInvoice = $entetepieceRepository->getLineCountByInvoiceIds($invoiceIds);
        $invalidLineCountByInvoice = $entetepieceRepository->getInvalidLineCountByInvoiceIds($invoiceIds);
        $workflowByInvoice = [];

        foreach ($pagination['items'] as $piece) {
            $pieceId = (int) $piece->getId();
            $lineCount = (int) ($lineCountByInvoice[$pieceId] ?? 0);
            $invalidLineCount = (int) ($invalidLineCountByInvoice[$pieceId] ?? 0);
            $isPerimee = $this->isPerimeeStatus($piece->getStatut());
            $isInvoiceType = $this->isInvoiceType($piece->getType());
            $isActive = $this->normalizeStatus($piece->getStatut()) === 'active';
            $isValidee = $this->isValideeStatus($piece->getStatut());
            $transitionTargets = $this->getTransitionTargetsForType($piece->getType());
            $transitionReason = null;
            $transitionEnabled = false;

            if (!$isPerimee && !$isInvoiceType && $isValidee) {
                $transitionReason = $this->getTransitionDisabledReason($piece, $lineCount, $invalidLineCount);
                $transitionEnabled = $transitionReason === null && $transitionTargets !== [];
            }

            $workflowByInvoice[$pieceId] = [
                'isPerimee' => $isPerimee,
                'isInvoiceType' => $isInvoiceType,
                'showView' => $isPerimee,
                'showEdit' => !$isPerimee,
                'showTransition' => !$isPerimee && !$isInvoiceType && $isValidee,
                'showEinvoicing' => !$isPerimee && $isValidee,
                'transitionEnabled' => $transitionEnabled,
                'transitionDisabledReason' => $transitionReason,
                'transitionTargets' => $transitionTargets,
                'statusKey' => $this->normalizeStatus($piece->getStatut()),
                'statusLabel' => $this->getStatusDisplayLabel($piece->getStatut()),
            ];
        }

        if ($forcedTierLabel === null) {
            if (is_array($forcedTierType)) {
                $forcedTierLabel = implode(' / ', array_values(array_filter(array_map(
                    static fn (mixed $type): string => trim((string) $type),
                    $forcedTierType
                ))));
            } elseif (is_string($forcedTierType)) {
                $forcedTierLabel = trim($forcedTierType);
            }
        }

        $inlineDevises = array_map(static function (Devises $devise): array {
            return [
                'id' => (int) ($devise->getId() ?? 0),
                'code' => (string) ($devise->getCode() ?? ''),
                'label' => (string) ($devise->getLibelle() ?? ''),
            ];
        }, $this->doctrine2->getRepository(Devises::class)->findBy([], ['code' => 'ASC']));

        $inlineReglements = array_map(static function (Reglement $reglement): array {
            return [
                'id' => (int) ($reglement->getId() ?? 0),
                'label' => (string) ($reglement->getLibelle() ?? ''),
            ];
        }, $this->doctrine2->getRepository(Reglement::class)->findBy([], ['libelle' => 'ASC']));

        $originToken = $this->resolvePieceOriginTokenByListRoute($listRoute);
        $inlineTierTypes = array_map(static fn (string $value): array => [
            'value' => $value,
            'label' => $value,
        ], $this->resolveAllowedTierTypesForOrigin($originToken));

        $inlinePieceTypes = array_map(static fn (string $value): array => [
            'value' => $value,
            'label' => $value,
        ], ['Devis', 'Commande', 'BL', 'Facture']);

        $inlinePieceStatuses = array_map(static fn (string $value): array => [
            'value' => $value,
            'label' => $value,
        ], ['Brouillon', 'Active', 'Validee']);

        return $this->render('entetepiece/index.html.twig', [
            'search' => $searchForm->createView(),
            'entetepieces' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
            'remiseByInvoice' => $remiseByInvoice,
            'amountByInvoice' => $amountByInvoice,
            'lineCountByInvoice' => $lineCountByInvoice,
            'workflowByInvoice' => $workflowByInvoice,
            'listRoute' => $listRoute,
            'origin' => $originToken,
            'forcedTierLabel' => $forcedTierLabel,
            'inlineDevises' => $inlineDevises,
            'inlineReglements' => $inlineReglements,
            'inlineTierTypes' => $inlineTierTypes,
            'inlinePieceTypes' => $inlinePieceTypes,
            'inlinePieceStatuses' => $inlinePieceStatuses,
        ]);
    }

    /**
     * @param array<int, Entetepiece> $pieces
     */
    private function hydrateTierNames(array $pieces): void
    {
        foreach ($pieces as $piece) {
            $piece->setDoctrine($this->doctrine2);
            $piece->setResolvedTierName($piece->getTierName($this->doctrine2));
        }
    }

    private function validateTierSelection(Entetepiece $piece, FormInterface $form): object|false|null
    {
        $tierType = $this->normalizeTierType($piece->getTypet());
        if ($tierType === 'vat' || $tierType === '') {
            if ($tierType === 'vat') {
                $piece->setTierId(null);
            }

            return null;
        }

        if (!in_array($tierType, ['client', 'prospect', 'fournisseur'], true)) {
            $form->get('typet')->addError(new FormError('Le type de tiers est invalide.'));
            $this->addFlash('warning', 'Le type de tiers sélectionné est invalide.');

            return false;
        }

        if ($piece->getTierId() === null) {
            $form->get('tierSelector')->addError(new FormError('Veuillez sélectionner un tiers.'));
            $this->addFlash('warning', 'Veuillez sélectionner un tiers.');

            return false;
        }

        $tier = $this->resolveTierEntity($piece);
        if ($tier === null) {
            $form->get('tierSelector')->addError(new FormError('Le tiers sélectionné est introuvable dans le dossier courant.'));
            $this->addFlash('warning', 'Le tiers sélectionné est introuvable dans le dossier courant.');

            return false;
        }

        $piece->setResolvedTierName($this->extractTierName($tier));

        return $tier;
    }

    private function resolveTierEntity(Entetepiece $piece): ?object
    {
        $tierClass = $this->resolveTierClass($piece->getTypet());
        $tierId = $piece->getTierId();
        if ($tierClass === null || $tierId === null) {
            return null;
        }

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return null;
        }

        return $this->doctrine2->getRepository($tierClass)->findOneBy([
            'id' => $tierId,
            'dossier' => $currentDossier,
        ]);
    }

    private function extractTierReglement(object|false|null $tier): ?Reglement
    {
        if (!$tier || !method_exists($tier, 'getReglement')) {
            return null;
        }

        $reglement = $tier->getReglement();

        return $reglement instanceof Reglement ? $reglement : null;
    }

    private function extractTierName(?object $tier): string
    {
        if ($tier !== null && method_exists($tier, 'getNom')) {
            $name = trim((string) $tier->getNom());
            if ($name !== '') {
                return $name;
            }
        }

        if ($tier !== null && method_exists($tier, '__toString')) {
            $name = trim((string) $tier);
            if ($name !== '') {
                return $name;
            }
        }

        return 'N/A';
    }

    private function resolveTierClass(?string $typet): ?string
    {
        return match ($this->normalizeTierType($typet)) {
            'client' => Clients::class,
            'prospect' => Prospects::class,
            'fournisseur' => Fournisseur::class,
            default => null,
        };
    }

    private function getAndIncrementDossierCounter(?string $pieceType, ?Dossier $dossier): int
    {
        if ($dossier === null) {
            return 1;
        }

        $normalized = $this->normalizePieceType($pieceType);

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

    private function buildPieceLinesRedirectUrl(int $pieceId, ?string $origin = null): string
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
            'client', 'prospect' => 'client',
            default => null,
        };
    }

    private function resolvePieceOriginTokenByListRoute(string $listRoute): ?string
    {
        return match ($listRoute) {
            'entetepiece.fournisseur_list' => 'fournisseur',
            'entetepiece.client_list', 'entetepiece.list' => 'client',
            default => null,
        };
    }

    private function resolvePieceListRoute(?string $origin): string
    {
        return $origin === 'fournisseur' ? 'entetepiece.fournisseur_list' : 'entetepiece.client_list';
    }

    /**
     * @return array<string, string>
     */
    private function buildPieceOriginQueryParams(?string $origin): array
    {
        return $origin !== null ? ['origin' => $origin] : [];
    }

    /**
     * @param array<int, Lignepiece> $lines
     * @return array{eligible: bool, reason: ?string, lineCount: int, invalidLineCount: int}
     */
    private function evaluateTransitionEligibility(Entetepiece $piece, array $lines): array
    {
        $lineStats = $this->buildLineStats($lines);
        $lineCount = $lineStats['lineCount'];
        $invalidLineCount = $lineStats['invalidLineCount'];

        $reason = $this->getTransitionDisabledReason($piece, $lineCount, $invalidLineCount);
        $eligible = $reason === null && $this->getTransitionTargetsForType($piece->getType()) !== [];

        return [
            'eligible' => $eligible,
            'reason' => $reason,
            'lineCount' => $lineCount,
            'invalidLineCount' => $invalidLineCount,
        ];
    }

    private function getTransitionDisabledReason(Entetepiece $piece, int $lineCount, int $invalidLineCount): ?string
    {
        if ($this->isPerimeeStatus($piece->getStatut())) {
            return 'Cette piece est perimee et ne peut plus etre modifiee';
        }

        if ($this->isInvoiceType($piece->getType())) {
            return 'Cette piece est deja au stade final Facture';
        }

        if (!$this->isValideeStatus($piece->getStatut())) {
            return 'Validez la piece pour pouvoir la convertir';
        }

        if ($lineCount <= 0) {
            return 'Ajoutez au moins une ligne avant la conversion';
        }

        if ($invalidLineCount > 0) {
            return 'Completez toutes les lignes (article, quantite, prix)';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function getTransitionTargetsForType(?string $pieceType): array
    {
        return match ($this->normalizePieceType($pieceType)) {
            'devis' => ['Commande', 'BL', 'Facture'],
            'commande' => ['BL', 'Facture'],
            'bl' => ['Facture'],
            default => [],
        };
    }

    private function resolveAllowedCreatePieceType(?string $pieceType): ?string
    {
        return match ($this->normalizePieceType($pieceType)) {
            'devis' => 'Devis',
            'commande' => 'Commande',
            'bl' => 'BL',
            'facture' => 'Facture',
            default => null,
        };
    }

    /**
     * @param array<int, string> $allowedTargets
     */
    private function resolveAllowedTargetType(string $targetType, array $allowedTargets): ?string
    {
        $normalizedTarget = $this->normalizePieceType($targetType);
        foreach ($allowedTargets as $candidate) {
            if ($this->normalizePieceType($candidate) === $normalizedTarget) {
                return $candidate;
            }
        }

        return null;
    }

    private function isLineComplete(Lignepiece $line): bool
    {
        return $line->getArticle() !== null
            && (float) ($line->getQte() ?? 0) > 0
            && (float) ($line->getPub() ?? 0) > 0;
    }

    private function isInvoiceType(?string $pieceType): bool
    {
        return $this->normalizePieceType($pieceType) === 'facture';
    }

    private function isPerimeeStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), ['perimee', 'perime', 'archivee', 'archive'], true);
    }

    private function isValideeStatus(?string $status): bool
    {
        return in_array($this->normalizeStatus($status), ['validee', 'valide'], true);
    }

    private function normalizePieceType(?string $pieceType): string
    {
        return $this->normalizeToken($pieceType);
    }

    private function normalizeStatus(?string $status): string
    {
        return $this->normalizeToken($status);
    }

    private function normalizeTierType(?string $type): string
    {
        return $this->normalizeToken($type);
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

    private function getStatusDisplayLabel(?string $status): string
    {
        return match ($this->normalizeStatus($status)) {
            'brouillon' => 'Brouillon',
            'active' => 'Active',
            'validee', 'valide' => 'Validee',
            'perimee', 'perime', 'archivee', 'archive' => 'Perimee',
            default => trim((string) $status) !== '' ? (string) $status : '-',
        };
    }

    /**
     * @return array<int, string>
     */
    private function resolveAllowedTierTypesForOrigin(?string $origin): array
    {
        return $origin === 'fournisseur'
            ? ['Fournisseur']
            : ['Client', 'Prospect'];
    }

    /**
     * @param array<int, string> $allowedTierTypes
     */
    private function resolveAllowedTierType(?string $tierType, array $allowedTierTypes): ?string
    {
        $normalizedTierType = $this->normalizeTierType($tierType);
        if ($normalizedTierType === '') {
            return null;
        }

        foreach ($allowedTierTypes as $allowedTierType) {
            if ($this->normalizeTierType($allowedTierType) === $normalizedTierType) {
                return $allowedTierType;
            }
        }

        return null;
    }

    private function resolveEditableStatusValue(?string $status): ?string
    {
        return match ($this->normalizeStatus($status)) {
            'brouillon' => 'Brouillon',
            'active' => 'Active',
            'validee', 'valide' => 'Validee',
            default => null,
        };
    }

    /**
     * @param array{lineCount: int, invalidLineCount: int} $lineStats
     * @return array<string, mixed>
     */
    private function serializePieceListItem(
        Entetepiece $piece,
        ManagerRegistry $doctrine,
        ?string $origin,
        array $lineStats
    ): array {
        $lineCount = (int) ($lineStats['lineCount'] ?? 0);
        $invalidLineCount = (int) ($lineStats['invalidLineCount'] ?? 0);
        $statusKey = $this->normalizeStatus($piece->getStatut());
        $isPerimee = $this->isPerimeeStatus($piece->getStatut());
        $isInvoiceType = $this->isInvoiceType($piece->getType());
        $isValidee = $this->isValideeStatus($piece->getStatut());
        $transitionTargets = $this->getTransitionTargetsForType($piece->getType());
        $transitionReason = null;
        $transitionEnabled = false;

        if (!$isPerimee && !$isInvoiceType && $isValidee) {
            $transitionReason = $this->getTransitionDisabledReason($piece, $lineCount, $invalidLineCount);
            $transitionEnabled = $transitionReason === null && $transitionTargets !== [];
        }

        $tierLabel = trim((string) $piece->getTierName($doctrine));
        if ($tierLabel === '' || strtoupper($tierLabel) === 'N/A') {
            $tierLabel = '____';
        }

        $datep = $piece->getDatep();

        return [
            'id' => (int) ($piece->getId() ?? 0),
            'type' => (string) ($piece->getType() ?? ''),
            'typet' => (string) ($piece->getTypet() ?? ''),
            'tierId' => $piece->getTierId() !== null ? (int) $piece->getTierId() : null,
            'tier' => $tierLabel,
            'pieceno' => (int) ($piece->getPieceno() ?? 0),
            'pieceref' => (string) ($piece->getPieceref() ?? ''),
            'piecerefDisplay' => (string) ($piece->getPieceref() ?? '____'),
            'remise' => (float) ($piece->getRemise() ?? 0),
            'remiseDisplay' => number_format((float) ($piece->getRemise() ?? 0), 2, ',', ' ') . '%',
            'montant' => (float) ($piece->getMontant() ?? 0),
            'montantDisplay' => number_format((float) ($piece->getMontant() ?? 0), 2, ',', ' '),
            'lignes' => $lineCount,
            'statut' => (string) ($piece->getStatut() ?? ''),
            'statutLabel' => $this->getStatusDisplayLabel($piece->getStatut()),
            'statusKey' => $statusKey,
            'deviseId' => $piece->getDevise()?->getId(),
            'devise' => (string) ($piece->getDevise()?->getCode() ?? ''),
            'annee' => $datep ? $datep->format('Y') : '-',
            'lineCount' => $lineCount,
            'canEdit' => !$isPerimee,
            'canView' => $isPerimee,
            'canOpenEinvoicing' => !$isPerimee && $isValidee,
            'transitionTargets' => $transitionTargets,
            'transitionEnabled' => $transitionEnabled,
            'transitionDisabledReason' => $transitionReason,
            'inlineUpdateUrl' => $this->generateUrl('entetepiece.inline_update', array_merge(
                ['id' => (int) ($piece->getId() ?? 0)],
                $this->buildPieceOriginQueryParams($origin)
            )),
            'editUrl' => $this->generateUrl('entetepiece.edit', array_merge(
                ['id' => (int) ($piece->getId() ?? 0)],
                $this->buildPieceOriginQueryParams($origin)
            )),
            'deleteUrl' => $this->generateUrl('entetepiece.delete', array_merge(
                ['id' => (int) ($piece->getId() ?? 0)],
                $this->buildPieceOriginQueryParams($origin)
            )),
        ];
    }

    /**
     * @param array<int, Lignepiece> $lines
     * @return array{lineCount: int, invalidLineCount: int}
     */
    private function buildLineStats(array $lines): array
    {
        $lineCount = count($lines);
        $invalidLineCount = 0;

        foreach ($lines as $line) {
            if (!$this->isLineComplete($line)) {
                ++$invalidLineCount;
            }
        }

        return [
            'lineCount' => $lineCount,
            'invalidLineCount' => $invalidLineCount,
        ];
    }

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    private function validateStatusProgression(
        ?string $fromStatus,
        ?string $toStatus,
        int $lineCount,
        int $invalidLineCount
    ): array {
        $from = $this->normalizeStatus($fromStatus);
        $to = $this->normalizeStatus($toStatus);

        if ($this->isPerimeeStatus($fromStatus)) {
            return [
                'allowed' => false,
                'reason' => 'Cette piece est perimee et ne peut plus changer de statut.',
            ];
        }

        if ($to === '' || !in_array($to, ['brouillon', 'active', 'validee', 'valide'], true)) {
            return [
                'allowed' => false,
                'reason' => 'Le statut cible est invalide.',
            ];
        }

        if (in_array($to, ['validee', 'valide'], true) && $lineCount <= 0) {
            return [
                'allowed' => false,
                'reason' => 'Ajoutez au moins une ligne avant de passer en Validee.',
            ];
        }

        if (in_array($to, ['validee', 'valide'], true) && $invalidLineCount > 0) {
            return [
                'allowed' => false,
                'reason' => 'Completez toutes les lignes (article, quantite, prix) avant de passer en Validee.',
            ];
        }

        if ($to === 'active' && in_array($from, ['brouillon', 'active'], true) && $lineCount <= 0) {
            return [
                'allowed' => false,
                'reason' => 'Ajoutez au moins une ligne avant de passer en Active.',
            ];
        }

        if ($from === $to) {
            return [
                'allowed' => true,
                'reason' => null,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
        ];
    }
}
