<?php

namespace App\Controller;

use App\Service\CodeOperationMigrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/code-operation-migration')]
class CodeOperationMigrationController extends AbstractController
{
    #[Route('', name: 'admin.code_operation_migration', methods: ['GET'])]
    public function index(CodeOperationMigrationService $migrationService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_COMPTABLE');

        return $this->render('admin/code_operation_migration.html.twig', [
            'status' => $migrationService->getStatus(),
        ]);
    }

    #[Route('/run', name: 'admin.code_operation_migration_run', methods: ['POST'])]
    public function run(Request $request, CodeOperationMigrationService $migrationService): RedirectResponse
    {
        $this->denyAccessUnlessGranted('ROLE_COMPTABLE');

        if (!$this->isCsrfTokenValid('run_code_operation_migration', (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton invalide.');

            return $this->redirectToRoute('admin.code_operation_migration');
        }

        $mode = strtolower(trim((string) $request->request->get('mode', 'auto')));
        $limit = (int) $request->request->get('limit', 200);

        if ($mode === 'manual') {
            $result = $migrationService->runManualMigration($limit);
            $this->addFlash(
                'success',
                sprintf(
                    'Migration manuelle terminée: +%d clients, +%d fournisseurs, +%d internes, +%d lignes.',
                    (int) ($result['updatedClientPieces'] ?? 0),
                    (int) ($result['updatedSupplierPieces'] ?? 0),
                    (int) ($result['updatedInternalPieces'] ?? 0),
                    (int) ($result['filledLineSens'] ?? 0)
                )
            );
        } else {
            $result = $migrationService->runAutomaticMigration();
            $this->addFlash(
                'success',
                sprintf(
                    'Migration automatique terminée: +%d clients, +%d fournisseurs, +%d internes, +%d lignes.',
                    (int) ($result['updatedClientPieces'] ?? 0),
                    (int) ($result['updatedSupplierPieces'] ?? 0),
                    (int) ($result['updatedInternalPieces'] ?? 0),
                    (int) ($result['filledLineSens'] ?? 0)
                )
            );
        }

        return $this->redirectToRoute('admin.code_operation_migration');
    }
}
