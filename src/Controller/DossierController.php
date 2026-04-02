<?php

namespace App\Controller;

use App\Entity\Dossier;
use App\Entity\Theme;
use App\Form\DossierFormType;
use App\Form\SearchGenericFormType;
use App\Model\SearchGeneric;
use App\Repository\DossierRepository;
use App\Repository\ThemeRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('dossier')]
class DossierController extends AbstractController
{
    #[Route('/', name: 'dossier.list')]
    public function index(Request $request, DossierRepository $dossierRepository): Response
    {
        $page = $request->query->getInt('page', 1);
        $searchData = new SearchGeneric();
        $searchForm = $this->createForm(SearchGenericFormType::class, $searchData, ['placeholder' => 'Rechercher par nom ou adresse...']);
        $searchForm->handleRequest($request);

        $searchActive = null;
        if ($searchForm->isSubmitted() && $searchForm->isValid()) {
            $searchActive = $searchData;
        }

        $pagination = $dossierRepository->findPaginated($searchActive, $page);

        return $this->render('dossier/index.html.twig', [
            'search' => $searchForm->createView(),
            'dossiers' => $pagination['items'],
            'currentPage' => $pagination['currentPage'],
            'totalPages' => $pagination['totalPages'],
            'totalItems' => $pagination['totalItems'],
        ]);
    }

    #[Route('/edit/{id?0}', name: 'dossier.edit')]
    public function addDossier(ManagerRegistry $doctrine, Request $request, ThemeRepository $themeRepository, int $id = 0): Response
    {
        $repository = $doctrine->getRepository(Dossier::class);
        $dossier = $repository->find($id);
        $new = false;

        if (!$dossier) {
            $dossier = new Dossier();
            $new = true;
        }

        if ($dossier->getTheme() === null) {
            $defaultTheme = $themeRepository->findDefaultTheme();
            if ($defaultTheme instanceof Theme) {
                $dossier->setTheme($defaultTheme);
            }
        }

        $form = $this->createForm(DossierFormType::class, $dossier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $logoFile = $form->get('logoFile')->getData();
            if ($logoFile) {
                $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/dossiers';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $newFilename = uniqid('logo_', true) . '.' . $logoFile->guessExtension();
                $logoFile->move($uploadDir, $newFilename);
                $dossier->setLogo('/uploads/dossiers/' . $newFilename);
            }

            $message = $new
                ? 'Le dossier est ajoute avec succes'
                : 'Le dossier a ete mis a jour avec succes';

            $entityManager = $doctrine->getManager();
            $entityManager->persist($dossier);
            $entityManager->flush();

            $this->addFlash('success', $message);
            return $this->redirectToRoute('dossier.list');
        }

        return $this->render('dossier/add-dossier.html.twig', [
            'dossier' => $form->createView(),
            'id' => $id,
        ]);
    }

    #[Route('/theme/create', name: 'dossier.theme.create', methods: ['POST'])]
    public function createTheme(Request $request, ManagerRegistry $doctrine, ThemeRepository $themeRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $payload = $this->parseRequestPayload($request);

        $csrfToken = (string) ($payload['_token'] ?? '');
        if (!$this->isCsrfTokenValid('create_dossier_theme', $csrfToken)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La session a expire. Veuillez reessayer.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 3) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le nom du theme doit contenir au moins 3 caracteres.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $primaryColor = strtoupper(trim((string) ($payload['primaryColor'] ?? '')));
        if (!$this->isValidHexColor($primaryColor)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La couleur primaire doit etre au format hexadecimal (#RRGGBB).',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $secondaryColor = $this->shadeHexColor($primaryColor, -0.22);
        $accentColor = $this->shadeHexColor($primaryColor, 0.24);
        $textColor = '#0F172A';
        $backgroundColor = $this->shadeHexColor($primaryColor, 0.92);

        $theme = new Theme();
        $theme
            ->setName($name)
            ->setCode($this->generateUniqueThemeCode($name, $themeRepository))
            ->setDescription(($payload['description'] ?? null) ? trim((string) $payload['description']) : null)
            ->setPrimaryColor($primaryColor)
            ->setSecondaryColor($secondaryColor)
            ->setAccentColor($accentColor)
            ->setTextColor($textColor)
            ->setBackgroundColor($backgroundColor)
            ->setIsSystem(false);

        $entityManager = $doctrine->getManager();
        $entityManager->persist($theme);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'theme' => $this->serializeTheme($theme),
            'message' => 'Le theme a ete cree avec succes.',
        ]);
    }

    #[Route('/theme/{id}/update', name: 'dossier.theme.update', methods: ['POST'])]
    public function updateTheme(int $id, Request $request, ManagerRegistry $doctrine, ThemeRepository $themeRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $theme = $themeRepository->find($id);
        if (!$theme instanceof Theme) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le theme demande est introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->parseRequestPayload($request);

        $csrfToken = (string) ($payload['_token'] ?? '');
        if (!$this->isCsrfTokenValid('manage_dossier_theme', $csrfToken)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La session a expire. Veuillez reessayer.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 3) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le nom du theme doit contenir au moins 3 caracteres.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $primaryColor = strtoupper(trim((string) ($payload['primaryColor'] ?? '')));
        if (!$this->isValidHexColor($primaryColor)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La couleur primaire doit etre au format hexadecimal (#RRGGBB).',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $secondaryColor = $this->shadeHexColor($primaryColor, -0.22);
        $accentColor = $this->shadeHexColor($primaryColor, 0.24);
        $textColor = '#0F172A';
        $backgroundColor = $this->shadeHexColor($primaryColor, 0.92);

        $theme
            ->setName($name)
            ->setDescription(($payload['description'] ?? null) ? trim((string) $payload['description']) : null)
            ->setPrimaryColor($primaryColor)
            ->setSecondaryColor($secondaryColor)
            ->setAccentColor($accentColor)
            ->setTextColor($textColor)
            ->setBackgroundColor($backgroundColor);

        $entityManager = $doctrine->getManager();
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'theme' => $this->serializeTheme($theme),
            'message' => 'Le theme a ete mis a jour avec succes.',
        ]);
    }

    #[Route('/theme/{id}/delete', name: 'dossier.theme.delete', methods: ['POST'])]
    public function deleteTheme(
        int $id,
        Request $request,
        ManagerRegistry $doctrine,
        ThemeRepository $themeRepository,
        DossierRepository $dossierRepository
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $theme = $themeRepository->find($id);
        if (!$theme instanceof Theme) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le theme demande est introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->parseRequestPayload($request);
        $csrfToken = (string) ($payload['_token'] ?? '');
        if (!$this->isCsrfTokenValid('manage_dossier_theme', $csrfToken)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La session a expire. Veuillez reessayer.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($theme->isSystem()) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Ce theme systeme ne peut pas etre supprime.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($themeRepository->count([]) <= 1) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Impossible de supprimer le dernier theme disponible.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager = $doctrine->getManager();
        $fallbackTheme = $this->findFallbackThemeForDeletion($theme, $themeRepository);
        $linkedDossiers = $dossierRepository->findBy(['theme' => $theme]);

        foreach ($linkedDossiers as $linkedDossier) {
            $linkedDossier->setTheme($fallbackTheme);
        }

        $deletedThemeName = (string) $theme->getName();
        $entityManager->remove($theme);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'deletedThemeId' => $id,
            'fallbackThemeId' => $fallbackTheme instanceof Theme ? $fallbackTheme->getId() : null,
            'message' => sprintf('Le theme "%s" a ete supprime avec succes.', $deletedThemeName),
        ]);
    }

    #[Route('/delete/{id}', name: 'dossier.delete')]
    public function deleteDossier(ManagerRegistry $doctrine, int $id): RedirectResponse
    {
        $repository = $doctrine->getRepository(Dossier::class);
        $dossier = $repository->find($id);

        if ($dossier) {
            $manager = $doctrine->getManager();
            $manager->remove($dossier);
            $manager->flush();

            $this->addFlash('success', 'Le dossier a ete supprime avec succes');
        } else {
            $this->addFlash('error', "Le dossier demande n'existe pas");
        }

        return $this->redirectToRoute('dossier.list');
    }

    private function isValidHexColor(string $color): bool
    {
        return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
    }

    private function normalizeHexColor(string $color, string $fallback): string
    {
        $normalized = strtoupper(trim($color));
        if ($this->isValidHexColor($normalized)) {
            return $normalized;
        }

        return strtoupper($fallback);
    }

    private function shadeHexColor(string $hexColor, float $percent): string
    {
        $hex = ltrim(strtoupper($hexColor), '#');
        if (strlen($hex) !== 6) {
            return '#475569';
        }

        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        if ($percent < 0) {
            $factor = 1 + $percent;
            $red *= $factor;
            $green *= $factor;
            $blue *= $factor;
        } else {
            $red = $red + ((255 - $red) * $percent);
            $green = $green + ((255 - $green) * $percent);
            $blue = $blue + ((255 - $blue) * $percent);
        }

        $red = max(0, min(255, (int) round($red)));
        $green = max(0, min(255, (int) round($green)));
        $blue = max(0, min(255, (int) round($blue)));

        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }

    private function generateUniqueThemeCode(string $name, ThemeRepository $themeRepository): string
    {
        $slugger = new AsciiSlugger();
        $baseCode = strtolower($slugger->slug($name)->toString());
        if ($baseCode === '') {
            $baseCode = 'theme';
        }

        $code = $baseCode;
        $counter = 2;

        while ($themeRepository->findOneBy(['code' => $code]) instanceof Theme) {
            $code = $baseCode . '-' . $counter;
            ++$counter;
        }

        return $code;
    }

    private function parseRequestPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            $payload = $request->request->all();
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array{
     *     id: int|null,
     *     code: string,
     *     name: string,
     *     description: string,
     *     primaryColor: string,
     *     secondaryColor: string,
     *     accentColor: string,
     *     textColor: string,
     *     backgroundColor: string,
     *     isSystem: bool
     * }
     */
    private function serializeTheme(Theme $theme): array
    {
        return [
            'id' => $theme->getId(),
            'code' => (string) $theme->getCode(),
            'name' => (string) $theme->getName(),
            'description' => $theme->getDescription() ?? 'Theme personnalise',
            'primaryColor' => (string) $theme->getPrimaryColor(),
            'secondaryColor' => (string) $theme->getSecondaryColor(),
            'accentColor' => (string) $theme->getAccentColor(),
            'textColor' => (string) $theme->getTextColor(),
            'backgroundColor' => (string) $theme->getBackgroundColor(),
            'isSystem' => $theme->isSystem(),
        ];
    }

    private function findFallbackThemeForDeletion(Theme $themeToDelete, ThemeRepository $themeRepository): ?Theme
    {
        $defaultTheme = $themeRepository->findDefaultTheme();
        if ($defaultTheme instanceof Theme && $defaultTheme->getId() !== $themeToDelete->getId()) {
            return $defaultTheme;
        }

        return $themeRepository->createQueryBuilder('t')
            ->andWhere('t.id != :themeId')
            ->setParameter('themeId', $themeToDelete->getId())
            ->orderBy('t.isSystem', 'DESC')
            ->addOrderBy('t.name', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
