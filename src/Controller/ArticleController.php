<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Dossier;
use App\Entity\Tarifs;
use App\Entity\Unite;
use App\Entity\User;
use App\Form\ArticleFormType;
use App\Form\SearchArtFormType;
use App\Model\SearchDataArt;
use App\Repository\ArticleRepository;
use App\Repository\TarifsRepository;
use App\Repository\UniteRepository;
use Doctrine\Persistence\ManagerRegistry;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;



#[Route('article')]
class ArticleController extends AbstractController
{
    #[Route('/', name: 'article.list')]
    public function index(
        Request $request,
        ManagerRegistry $doctrine,
        ArticleRepository $artRepository,
        UniteRepository $uniteRepository,
        TarifsRepository $tarifsRepository
    ): Response
    {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;

        $page = $request->query->getInt('page', 1);
        $searchData = new SearchDataArt();
        $searchForm = $this->createForm(SearchArtFormType::class, $searchData);
        $searchForm->handleRequest($request);
        
        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $artRepository->findPaginated($searchActive, $page);
        $unites = $uniteRepository->findBy([], ['libelle' => 'ASC']);
        $tarifs = $tarifsRepository->getSearchQueryBuilder()->getQuery()->getResult();

        return $this->render('article/index.html.twig', [
            'search' => $searchForm->createView(),
            'articles' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
            'inlineUnites' => array_map(
                static fn (Unite $unite): array => [
                    'id' => $unite->getId(),
                    'label' => (string) $unite->getLibelle(),
                ],
                $unites
            ),
            'inlineTarifs' => array_map(
                static fn (Tarifs $tarif): array => [
                    'id' => $tarif->getId(),
                    'label' => (string) $tarif->getLibelle(),
                ],
                $tarifs
            ),
        ]);
    }

    #[Route('/create-minimal', name: 'article.create_minimal', methods: ['POST'])]
    public function createMinimalArticle(
        Request $request,
        ManagerRegistry $doctrine,
        UniteRepository $uniteRepository
    ): Response {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun dossier courant sélectionné.',
            ], 403);
        }

        if (!$this->isCsrfTokenValid('article_create_minimal', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Jeton de sécurité invalide.',
            ], 403);
        }

        $libelle = trim((string) $request->request->get('libelle', ''));
        if ($libelle === '') {
            return $this->json([
                'success' => false,
                'message' => 'La designation est obligatoire.',
            ], 422);
        }

        $uniteId = trim((string) $request->request->get('uniteId', ''));
        if ($uniteId === '' || !ctype_digit($uniteId)) {
            return $this->json([
                'success' => false,
                'message' => 'L unite est obligatoire.',
            ], 422);
        }

        $unite = $uniteRepository->find((int) $uniteId);
        if (!$unite instanceof Unite) {
            return $this->json([
                'success' => false,
                'message' => 'Unité introuvable.',
            ], 404);
        }

        $article = new Article();
        $article->setDossier($currentDossier);
        $article->setLibelle($libelle);
        $article->setUnite($unite);
        $article->setDoctrine($doctrine);
        if ($user instanceof User) {
            $article->setUser($user);
        }

        $imageFile = $request->files->get('imageFile');
        if ($imageFile !== null && !$imageFile instanceof UploadedFile) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun fichier image valide n a ete fourni.',
            ], 422);
        }

        if ($imageFile instanceof UploadedFile) {
            $validationError = $this->validateArticleImageFile($imageFile);
            if ($validationError !== null) {
                return $this->json([
                    'success' => false,
                    'message' => $validationError,
                ], 422);
            }
        }

        $entityManager = $doctrine->getManager();
        $entityManager->persist($article);
        $entityManager->flush();

        if ($imageFile instanceof UploadedFile) {
            $this->replaceArticleImage($article, $imageFile);
            $entityManager->flush();
        }

        return $this->json([
            'success' => true,
            'message' => 'Article créé avec succès.',
            'article' => $this->serializeArticle($article, true),
        ]);
    }

    #[Route('/import', name: 'article.import', methods: ['POST'])]
    public function importArticles(
        Request $request,
        ManagerRegistry $doctrine,
        ArticleRepository $articleRepository,
        UniteRepository $uniteRepository,
        TarifsRepository $tarifsRepository
    ): Response {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            $this->addFlash('error', 'Aucun dossier courant sélectionné.');
            return $this->redirectToRoute('article.list');
        }

        if (!$this->isCsrfTokenValid('article_import', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('article.list');
        }

        $file = $request->files->get('import_file');
        if (!$file instanceof UploadedFile) {
            $this->addFlash('error', 'Veuillez choisir un fichier Excel (.xlsx, .xls, .xlsm).');
            return $this->redirectToRoute('article.list');
        }

        $extension = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'xlsm'], true)) {
            $this->addFlash('error', 'Format non supporte. Utilisez un fichier .xlsx, .xls ou .xlsm.');
            return $this->redirectToRoute('article.list');
        }
        if (!class_exists(\ZipArchive::class) && in_array($extension, ['xlsx', 'xlsm'], true)) {
            $this->addFlash('error', 'Le serveur ne supporte pas ce format. Exportez puis importez le fichier .xls.');
            return $this->redirectToRoute('article.list');
        }

        try {
            $reader = IOFactory::createReaderForFile($file->getPathname());
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($file->getPathname());
        } catch (Throwable $e) {
            $this->addFlash('error', 'Impossible de lire le fichier Excel: ' . $e->getMessage());
            return $this->redirectToRoute('article.list');
        }

        $dataSheet = $spreadsheet->getSheetByName('Export') ?? $spreadsheet->getSheet(0);
        $headerConfig = $this->resolveArticleHeaderConfig($dataSheet);
        if ($headerConfig === null) {
            $this->addFlash('error', 'En-têtes introuvables. Le fichier doit contenir au minimum les colonnes ID et Désignation.');
            return $this->redirectToRoute('article.list');
        }

        $entityManager = $doctrine->getManager();
        $highestRow = $dataSheet->getHighestDataRow();
        $created = 0;
        $updated = 0;
        $ignored = 0;
        $errors = [];

        for ($rowIndex = $headerConfig['header_row'] + 1; $rowIndex <= $highestRow; $rowIndex++) {
            $rowData = $this->readArticleImportRow($dataSheet, $headerConfig['columns'], $rowIndex);
            if ($this->isArticleImportRowEmpty($rowData)) {
                continue;
            }

            $idRaw = trim((string) ($rowData['id'] ?? ''));
            $designation = trim((string) ($rowData['libelle'] ?? ''));
            $uniteRaw = trim((string) ($rowData['unite'] ?? ''));
            $tarifRaw = trim((string) ($rowData['tarif'] ?? ''));

            if ($idRaw !== '') {
                $idValue = preg_replace('/\D+/', '', $idRaw) ?? '';
                if ($idValue === '') {
                    $ignored++;
                    $errors[] = sprintf('Ligne %d: ID invalide "%s".', $rowIndex, $idRaw);
                    continue;
                }

                $article = $articleRepository->findOneBy([
                    'id' => (int) $idValue,
                    'dossier' => $currentDossier,
                ]);

                if ($article === null) {
                    $ignored++;
                    $errors[] = sprintf('Ligne %d: article #%s introuvable dans le dossier.', $rowIndex, $idRaw);
                    continue;
                }

                $resolvedUnite = null;
                if ($uniteRaw !== '') {
                    $resolvedUnite = $this->resolveUniteForImport($uniteRepository, $uniteRaw);
                    if ($resolvedUnite === null) {
                        $ignored++;
                        $errors[] = sprintf('Ligne %d : unité "%s" invalide ou introuvable.', $rowIndex, $uniteRaw);
                        continue;
                    }
                }

                $resolvedTarif = null;
                if ($tarifRaw !== '') {
                    $resolvedTarif = $this->resolveTarifForImport($tarifsRepository, $currentDossier, $tarifRaw);
                    if ($resolvedTarif === null) {
                        $ignored++;
                        $errors[] = sprintf('Ligne %d : tarif "%s" invalide ou introuvable.', $rowIndex, $tarifRaw);
                        continue;
                    }
                }

                if ($designation !== '') {
                    $article->setLibelle($designation);
                }
                if ($resolvedUnite instanceof Unite) {
                    $article->setUnite($resolvedUnite);
                }
                if ($resolvedTarif instanceof Tarifs) {
                    $article->setTarif($resolvedTarif);
                }

                $entityManager->persist($article);
                $updated++;
                continue;
            }

            if ($designation === '') {
                $ignored++;
                $errors[] = sprintf('Ligne %d : la désignation est obligatoire pour créer un article.', $rowIndex);
                continue;
            }

            $article = new Article();
            $article->setDossier($currentDossier);
            $article->setLibelle($designation);

            if ($uniteRaw !== '') {
                $unite = $this->resolveUniteForImport($uniteRepository, $uniteRaw);
                if ($unite === null) {
                    $ignored++;
                    $errors[] = sprintf('Ligne %d : unité "%s" invalide ou introuvable.', $rowIndex, $uniteRaw);
                    continue;
                }
                $article->setUnite($unite);
            }

            if ($tarifRaw !== '') {
                $tarif = $this->resolveTarifForImport($tarifsRepository, $currentDossier, $tarifRaw);
                if ($tarif === null) {
                    $ignored++;
                    $errors[] = sprintf('Ligne %d : tarif "%s" invalide ou introuvable.', $rowIndex, $tarifRaw);
                    continue;
                }
                $article->setTarif($tarif);
            }

            $entityManager->persist($article);
            $created++;
        }

        if (($created + $updated) > 0) {
            $entityManager->flush();
        }

        if (($created + $updated) > 0) {
            $this->addFlash(
                'success',
                sprintf('Import terminé : %d création(s), %d mise(s) à jour.', $created, $updated)
            );
        } else {
            $this->addFlash('warning', 'Aucune ligne importee.');
        }

        if ($ignored > 0) {
            $this->addFlash('warning', sprintf('%d ligne(s) ignoree(s).', $ignored));
        }

        if ($errors !== []) {
            $preview = implode(' | ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $preview .= ' | ...';
            }
            $this->addFlash('error', $preview);
        }

        return $this->redirectToRoute('article.list');
    }

    #[Route('/inline-update/{id<\d+>}', name: 'article.inline_update', methods: ['POST'])]
    public function inlineUpdateArticle(
        Request $request,
        ManagerRegistry $doctrine,
        UniteRepository $uniteRepository,
        TarifsRepository $tarifsRepository,
        int $id
    ): Response {
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun dossier courant sélectionné.',
            ], 403);
        }

        if (!$this->isCsrfTokenValid('article_inline_update', (string) $request->request->get('_token'))) {
            return $this->json([
                'success' => false,
                'message' => 'Jeton de sécurité invalide.',
            ], 403);
        }

        $repository = $doctrine->getRepository(Article::class);
        $article = $repository->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        if (!$article instanceof Article) {
            return $this->json([
                'success' => false,
                'message' => 'Article introuvable.',
            ], 404);
        }

        $libelle = trim((string) $request->request->get('libelle', ''));
        if ($libelle === '') {
            return $this->json([
                'success' => false,
                'message' => 'La designation est obligatoire.',
            ], 422);
        }

        $uniteId = trim((string) $request->request->get('uniteId', ''));
        $unite = null;
        if ($uniteId !== '') {
            if (!ctype_digit($uniteId)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Unite invalide.',
                ], 422);
            }

            $unite = $uniteRepository->find((int) $uniteId);
            if (!$unite instanceof Unite) {
                return $this->json([
                    'success' => false,
                    'message' => 'Unité introuvable.',
                ], 404);
            }
        }

        $tarifId = trim((string) $request->request->get('tarifId', ''));
        $tarif = null;
        if ($tarifId !== '') {
            if (!ctype_digit($tarifId)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tarif invalide.',
                ], 422);
            }

            $tarif = $tarifsRepository->findOneBy([
                'id' => (int) $tarifId,
                'dossier' => $currentDossier,
            ]);
            if (!$tarif instanceof Tarifs) {
                return $this->json([
                    'success' => false,
                    'message' => 'Tarif introuvable.',
                ], 404);
            }
        }

        $article->setLibelle($libelle);
        $article->setUnite($unite);
        $article->setTarif($tarif);
        $article->setDoctrine($doctrine);
        if ($user instanceof User) {
            $article->setUser($user);
        }

        $entityManager = $doctrine->getManager();
        $entityManager->persist($article);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Article mis à jour avec succès.',
            'article' => $this->serializeArticle($article),
        ]);
    }
   

    #[Route('/{id<\d+>}/upload-image', name: 'article.upload_image', methods: ['POST'])]
    public function uploadImage(
        Request $request,
        ManagerRegistry $doctrine,
        int $id
    ): JsonResponse {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'RequÃªte invalide.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        if ($currentDossier === null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun dossier courant sÃ©lectionnÃ©.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (!$this->isCsrfTokenValid('article_upload_image', (string) $request->request->get('_token'))) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Jeton de sÃ©curitÃ© invalide.',
            ], Response::HTTP_FORBIDDEN);
        }

        $repository = $doctrine->getRepository(Article::class);
        $article = $repository->findOneBy([
            'id' => $id,
            'dossier' => $currentDossier,
        ]);

        if (!$article instanceof Article) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Article introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($request->request->getBoolean('removeImage')) {
            try {
                $this->removeArticleImage($article);

                $entityManager = $doctrine->getManager();
                $entityManager->persist($article);
                $entityManager->flush();
            } catch (Throwable $exception) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Impossible de supprimer l image de l article.',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return new JsonResponse([
                'success' => true,
                'imageUrl' => '',
                'message' => 'Image supprimée avec succès',
                'article' => $this->serializeArticle($article),
            ]);
        }

        $imageFile = $request->files->get('image');
        if (!$imageFile instanceof UploadedFile) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Aucun fichier image n a ete fourni.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validationError = $this->validateArticleImageFile($imageFile);
        if ($validationError !== null) {
            return new JsonResponse([
                'success' => false,
                'message' => $validationError,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $this->replaceArticleImage($article, $imageFile);

            $entityManager = $doctrine->getManager();
            $entityManager->persist($article);
            $entityManager->flush();
        } catch (Throwable $exception) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Impossible d enregistrer l image de l article.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'success' => true,
            'imageUrl' => $article->getImage() ?? '',
            'message' => 'Image enregistrée avec succès',
            'article' => $this->serializeArticle($article),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'article.detail')]
    public function detail(ManagerRegistry $doctrine,$id): Response
    {
        $repository = $doctrine->getRepository(Article::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $article = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
        
       if(!$article){
            $this->addFlash(
            'error',
            "Le article n'existe pas"
            );
            return $this->redirectToRoute('article.list');
       }
        
        return $this->render('article/detail.html.twig', [
            'article' => $article
        ]);
    }
    
    #[Route('/edit/{id?0}', name: 'article.edit')]
    public function addArticle(ManagerRegistry $doctrine, Request $request, $id): Response
    {
       // $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Article::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $article = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
        $new = false;
        if(!$article){
            $article = new Article();
            $new = true;
            if ($currentDossier instanceof Dossier) {
                $article->setDossier($currentDossier);
            }
        }
        $article->doctrine=$doctrine;
        $article->user=$this->getUser();

       $form = $this->createForm(ArticleFormType::class, $article);
       $form->handleRequest($request);
       if($form->isSubmitted() && $form->isValid()){
        $imageFile = $form->get('imageFile')->getData();
        $removeImage = $request->request->getBoolean('remove_image');

        If ($new){
            $message = "Le article est ajouté avec succès";
            //$article->setCreatedBy($this->getUser());
            //$article->setCreatedAt(new \DateTimeImmutable('now'));
        }else{
            $message = "Le article a été mis à jour avec succès";
            //$article->setModifedBy($this->getUser());
            //$article->setModifedAt(new \DateTimeImmutable('now'));
        }
        $entityManager = $doctrine->getManager();
        $entityManager->persist($article);
        if ($new && $imageFile instanceof UploadedFile) {
            $entityManager->flush();
        }

        if ($imageFile instanceof UploadedFile) {
            $this->replaceArticleImage($article, $imageFile);
        } elseif ($removeImage) {
            $this->removeArticleImage($article);
        }

        $entityManager->flush();
        
        $this->addFlash(
           'success',
           $message
        );
        return $this->redirectToRoute('article.list');
        
       }else{
            return $this->render('article/add-article.html.twig', [
                //'article' => $article,
                'form' => $form->createView(),
                'articleEntity' => $article,
                /*'eleves' => $eleves,
                'cotisations' => $cotisations,*/
                'id' => $id
            ]);
       }
        
    }

    #[Route('/delete/{id}', name: 'article.delete')]
    public function deleteArticle(ManagerRegistry $doctrine, $id): Response
    {
        //$this->denyAccessUnlessGranted('ROLE_ADMIN');
        $repository = $doctrine->getRepository(Article::class);
        $user = $this->getUser();
        $currentDossier = $user instanceof User ? $user->getCurrentDossier() : null;
        $article = $repository->findOneBy(['id' => $id, 'dossier' => $currentDossier]);
        if($article){
            $this->removeArticleImage($article);
            $manager = $doctrine->getManager();
            $manager->remove($article);
            $manager->flush();
            $this->addFlash(
               'success',
               "L'article a été supprimé avec succès"
            );
        }else{
            $this->addFlash(
                'error',
                "L'article demandé n'existe pas"
             );
        }
        return $this->redirectToRoute('article.list');
    }

    /**
     * @return array{
     *     id: int|null,
     *     libelle: string,
     *     uniteId: int|null,
     *     uniteLabel: string,
     *     tarifId: int|null,
     *     tarifLabel: string,
     *     imageUrl: string,
     *     uploadImageUrl: string,
     *     inlineUpdateUrl?: string,
     *     detailUrl?: string,
     *     deleteUrl?: string
     * }
     */
    private function serializeArticle(Article $article, bool $includeRowUrls = false): array
    {
        $data = [
            'id' => $article->getId(),
            'libelle' => (string) $article->getLibelle(),
            'uniteId' => $article->getUnite()?->getId(),
            'uniteLabel' => $article->getUnite()?->getLibelle() ?? '',
            'tarifId' => $article->getTarif()?->getId(),
            'tarifLabel' => $article->getTarif()?->getLibelle() ?? '',
            'imageUrl' => $article->getImage() ?? '',
            'uploadImageUrl' => $this->generateUrl('article.upload_image', ['id' => $article->getId()]),
        ];

        if ($includeRowUrls) {
            $data['inlineUpdateUrl'] = $this->generateUrl('article.inline_update', ['id' => $article->getId()]);
            $data['detailUrl'] = $this->generateUrl('article.detail', ['id' => $article->getId()]);
            $data['deleteUrl'] = $this->generateUrl('article.delete', ['id' => $article->getId()]);
        }

        return $data;
    }

    private function validateArticleImageFile(UploadedFile $imageFile): ?string
    {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
        ];

        if ($imageFile->getSize() > 2 * 1024 * 1024) {
            return 'Le fichier dépasse 2 Mo';
        }

        if (!in_array((string) $imageFile->getMimeType(), $allowedMimeTypes, true)) {
            return 'Seuls les fichiers PNG, JPG et WebP sont acceptés';
        }

        return null;
    }

    private function replaceArticleImage(Article $article, UploadedFile $imageFile): void
    {
        $validationError = $this->validateArticleImageFile($imageFile);
        if ($validationError !== null) {
            throw new \RuntimeException($validationError);
        }

        $articleId = $article->getId();
        if ($articleId === null) {
            throw new \RuntimeException('L article doit exister avant l upload de son image.');
        }

        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/articles';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            throw new \RuntimeException('Impossible de creer le dossier de stockage des images.');
        }

        $newFilename = sprintf('article-%d-%s.%s', $articleId, time(), $this->resolveArticleImageExtension($imageFile));
        $oldImage = $article->getImage();

        try {
            $imageFile->move($uploadDir, $newFilename);
        } catch (FileException $exception) {
            throw new \RuntimeException('Impossible d enregistrer l image de l article.', 0, $exception);
        }

        $article->setImage('/uploads/articles/' . $newFilename);
        $this->deleteArticleImageFile($oldImage);
    }

    private function removeArticleImage(Article $article): void
    {
        $currentImage = trim((string) $article->getImage());
        if ($currentImage === '') {
            return;
        }

        $this->deleteArticleImageFile($currentImage);
        $article->setImage(null);
    }

    private function deleteArticleImageFile(?string $imagePath): void
    {
        $relativePath = trim((string) $imagePath);
        if ($relativePath === '') {
            return;
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $uploadsRoot = str_replace('\\', '/', $projectDir . '/public/uploads/articles/');
        $absolutePath = str_replace('\\', '/', $projectDir . '/public/' . ltrim($relativePath, '/'));

        if (!str_starts_with($absolutePath, $uploadsRoot)) {
            return;
        }

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function resolveArticleImageExtension(UploadedFile $imageFile): string
    {
        return match ((string) $imageFile->getMimeType()) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
    }

    /**
     * @return array{header_row: int, columns: array<string, string>}|null
     */
    private function resolveArticleHeaderConfig(Worksheet $sheet): ?array
    {
        $highestColumnIndex = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $maxColIndex = min($highestColumnIndex, 26);

        for ($row = 1; $row <= 20; $row++) {
            $columns = [];
            for ($col = 1; $col <= $maxColIndex; $col++) {
                $column = Coordinate::stringFromColumnIndex($col);
                $rawHeader = trim((string) $sheet->getCell($column . $row)->getFormattedValue());
                if ($rawHeader === '') {
                    continue;
                }

                $normalized = $this->normalizeImportHeader($rawHeader);
                if ($normalized !== null && !isset($columns[$normalized])) {
                    $columns[$normalized] = $column;
                }
            }

            if (isset($columns['id'], $columns['libelle'])) {
                return [
                    'header_row' => $row,
                    'columns' => $columns,
                ];
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $columns
     * @return array{id: string, libelle: string, unite: string, tarif: string}
     */
    private function readArticleImportRow(Worksheet $sheet, array $columns, int $row): array
    {
        $extract = function (string $field) use ($sheet, $columns, $row): string {
            if (!isset($columns[$field])) {
                return '';
            }
            return trim((string) $sheet->getCell($columns[$field] . $row)->getFormattedValue());
        };

        return [
            'id' => $extract('id'),
            'libelle' => $extract('libelle'),
            'unite' => $extract('unite'),
            'tarif' => $extract('tarif'),
        ];
    }

    /**
     * @param array{id: string, libelle: string, unite: string, tarif: string} $row
     */
    private function isArticleImportRowEmpty(array $row): bool
    {
        return trim($row['id']) === ''
            && trim($row['libelle']) === ''
            && trim($row['unite']) === ''
            && trim($row['tarif']) === '';
    }

    private function normalizeImportHeader(string $header): ?string
    {
        $value = trim($header);
        if ($value === '') {
            return null;
        }

        $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = $translit === false ? $value : $translit;
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';

        return match ($normalized) {
            'id' => 'id',
            'designation', 'dsignation', 'libelle', 'libellearticle' => 'libelle',
            'unite', 'unit', 'unitearticle', 'unitarticle', 'codeunite' => 'unite',
            'tarif', 'tarifs', 'tarifvente' => 'tarif',
            default => null,
        };
    }

    private function resolveUniteForImport(UniteRepository $uniteRepository, string $value): ?Unite
    {
        $input = trim($value);
        if ($input === '') {
            return null;
        }

        if (ctype_digit($input)) {
            $unite = $uniteRepository->find((int) $input);
            if ($unite instanceof Unite) {
                return $unite;
            }
        }

        $normalizedInput = $this->normalizeImportLookupValue($input);
        if ($normalizedInput === '') {
            return null;
        }

        foreach ($uniteRepository->findBy([], ['libelle' => 'ASC']) as $unite) {
            if (!$unite instanceof Unite) {
                continue;
            }

            if ($this->normalizeImportLookupValue((string) $unite->getLibelle()) === $normalizedInput) {
                return $unite;
            }

            $code = (string) $unite->getCode();
            if ($code !== '' && $this->normalizeImportLookupValue($code) === $normalizedInput) {
                return $unite;
            }
        }

        return null;
    }

    private function resolveTarifForImport(TarifsRepository $tarifsRepository, Dossier $dossier, string $value): ?Tarifs
    {
        $input = trim($value);
        if ($input === '') {
            return null;
        }

        if (ctype_digit($input)) {
            $tarif = $tarifsRepository->findOneBy(['id' => (int) $input, 'dossier' => $dossier]);
            if ($tarif instanceof Tarifs) {
                return $tarif;
            }
        }

        $normalizedInput = $this->normalizeImportLookupValue($input);
        if ($normalizedInput === '') {
            return null;
        }

        foreach ($tarifsRepository->findBy(['dossier' => $dossier], ['libelle' => 'ASC']) as $tarif) {
            if (!$tarif instanceof Tarifs) {
                continue;
            }

            if ($this->normalizeImportLookupValue((string) $tarif->getLibelle()) === $normalizedInput) {
                return $tarif;
            }
        }

        return null;
    }

    private function normalizeImportLookupValue(string $value): string
    {
        $normalized = trim($value);
        if ($normalized === '') {
            return '';
        }

        $translit = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $normalized = $translit === false ? $normalized : $translit;
        $normalized = strtolower($normalized);

        return preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
    }
}
