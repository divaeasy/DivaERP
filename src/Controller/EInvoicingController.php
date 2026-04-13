<?php

namespace App\Controller;

use App\Entity\Entetepiece;
use App\Service\EInvoicing\FactureX\FactureXGenerator;
use App\Service\EInvoicing\FileStorageService;
use App\Service\EInvoicing\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invoice/e-invoicing', name: 'invoice_einvoicing_')]
class EInvoicingController extends AbstractController
{
    public function __construct(
        private InvoiceService $invoiceService,
        private FileStorageService $fileStorage,
        private EntityManagerInterface $entityManager,
        private ManagerRegistry $doctrine,
    ) {}

    #[Route('/{id}/test', name: 'test', methods: ['GET'])]
    public function testPage(Entetepiece $invoice): Response
    {
        $this->prepareInvoiceContext($invoice);
        $nonInvoiceResponse = $this->redirectIfNotEligibleForEinvoicing($invoice);
        if ($nonInvoiceResponse !== null) {
            return $nonInvoiceResponse;
        }

        $classicFilename = $this->fileStorage->getPdfFilenameForModel($invoice, FactureXGenerator::MODEL_CLASSIC);
        $modernFilename = $this->fileStorage->getPdfFilenameForModel($invoice, FactureXGenerator::MODEL_MODERN);
        $classicGenerated = $this->fileStorage->fileExists($classicFilename);
        $modernGenerated = $this->fileStorage->fileExists($modernFilename);

        return $this->render('e_invoicing/test.html.twig', [
            'invoice' => $invoice,
            'classic_generated' => $classicGenerated,
            'modern_generated' => $modernGenerated,
        ]);
    }

    #[Route('/{id}/generate-facturex', name: 'generate_facturex', methods: ['GET', 'POST'])]
    public function generateFactureX(Request $request, Entetepiece $invoice): Response
    {
        $this->prepareInvoiceContext($invoice);
        $nonInvoiceResponse = $this->redirectIfNotEligibleForEinvoicing($invoice);
        if ($nonInvoiceResponse !== null) {
            return $nonInvoiceResponse;
        }

        $model = (string) ($request->query->get('model') ?? $request->request->get('model') ?? FactureXGenerator::DEFAULT_MODEL);
        if ($invoice->getLignepieces()->isEmpty()) {
            return $this->redirectToPieceLinesWithWarning($invoice);
        }

        try {
            $result = $this->invoiceService->generateFactureX($invoice, $model);

            if ($result['success']) {
                $invoice->setFactureX(true);
                $this->entityManager->flush();

                $this->invoiceService->updateInvoiceStatus($invoice, 'FACTUREX_GENERATED', [
                    'pdf_filename' => $result['pdf_filename'],
                    'xml_filename' => $result['xml_filename'],
                    'pdf_model' => $result['pdf_model'] ?? FactureXGenerator::DEFAULT_MODEL,
                ]);

                $this->addFlash(
                    'success',
                    sprintf(
                        'Facture-X pour la piece %s generee avec succes (modele: %s).',
                        $this->getPieceTypeLabel($invoice),
                        (string) ($result['pdf_model'] ?? FactureXGenerator::DEFAULT_MODEL)
                    )
                );
            } else {
                $this->addFlash('error', 'Echec de la generation: ' . ($result['error'] ?? 'erreur inconnue'));
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('invoice_einvoicing_test', ['id' => $invoice->getId()]);
    }

    #[Route('/{id}/submit-tiime', name: 'submit_tiime', methods: ['GET', 'POST'])]
    public function submitToTiime(Entetepiece $invoice): Response
    {
        $this->prepareInvoiceContext($invoice);
        $nonInvoiceResponse = $this->redirectIfNotEligibleForEinvoicing($invoice);
        if ($nonInvoiceResponse !== null) {
            return $nonInvoiceResponse;
        }

        if ($invoice->getLignepieces()->isEmpty()) {
            return $this->redirectToPieceLinesWithWarning($invoice);
        }

        try {
            // If Facture-X already generated, use existing files instead of regenerating.
            if ($invoice->isFactureX() && $invoice->getFactureXPdfFilename() && $invoice->getFactureXXmlFilename()) {
                $pdfContent = $this->fileStorage->getFileContent($invoice->getFactureXPdfFilename());
                $xmlContent = $this->fileStorage->getFileContent($invoice->getFactureXXmlFilename());
                $result = $this->invoiceService->submitToTiime($invoice, $xmlContent, $pdfContent);

                if (isset($result['status']) && $result['status'] === 'submitted') {
                    $invoice->setSubmittedToTiime(true);
                    $invoice->setTiimeInvoiceId($result['tiime_invoice_id'] ?? null);
                    $invoice->setTiimeSubmissionId($result['submission_id'] ?? null);
                    $this->entityManager->flush();

                    $this->addFlash(
                        'success',
                        sprintf('Piece %s transmise au PDP Tiime.', $this->getPieceTypeLabel($invoice))
                    );
                    if (!empty($result['tiime_invoice_id'])) {
                        $this->addFlash('info', 'ID Tiime: ' . $result['tiime_invoice_id']);
                    }
                } else {
                    $this->addFlash('error', 'Echec de la transmission: ' . ($result['error'] ?? 'erreur inconnue'));
                }
            } else {
                // No Facture-X yet: run full workflow.
                $result = $this->invoiceService->processInvoice($invoice);

                if ($result['success']) {
                    $invoice->setFactureX(true);
                    $invoice->setSubmittedToTiime(true);
                    $this->entityManager->flush();

                    $this->addFlash(
                        'success',
                        sprintf(
                            'Facture-X pour la piece %s generee et transmise a Tiime.',
                            $this->getPieceTypeLabel($invoice)
                        )
                    );
                } else {
                    $this->addFlash('error', 'Echec: ' . ($result['error'] ?? 'erreur inconnue'));
                }
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur: ' . $e->getMessage());
        }

        return $this->redirectToRoute('invoice_einvoicing_test', ['id' => $invoice->getId()]);
    }

    private function redirectToPieceLinesWithWarning(Entetepiece $invoice): Response
    {
        $this->addFlash('warning', 'Cette pièce ne contient aucune ligne. Ajoutez au moins une ligne avant la génération.');

        $url = $this->generateUrl('entetepiece.edit', [
            'id' => $invoice->getId(),
            'scroll' => 'piece-lines',
        ]) . '#piece-lines';

        return $this->redirect($url);
    }

    private function redirectIfNotEligibleForEinvoicing(Entetepiece $invoice): ?Response
    {
        if ($this->isPerimeeStatus($invoice->getStatut())) {
            $this->addFlash(
                'warning',
                sprintf(
                    'Cette piece %s est perimee et ne peut plus etre traitee en e-facturation.',
                    $this->getPieceTypeLabel($invoice)
                )
            );

            return $this->redirectToRoute('entetepiece.edit', ['id' => $invoice->getId()]);
        }

        return null;
    }

    private function getPieceTypeLabel(Entetepiece $invoice): string
    {
        return match ($this->normalizeToken($invoice->getType())) {
            'devis' => 'Devis',
            'commande' => 'Commande',
            'bl' => 'BL',
            'facture' => 'Facture',
            default => trim((string) $invoice->getType()) !== '' ? (string) $invoice->getType() : 'Piece',
        };
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

    private function isPerimeeStatus(?string $status): bool
    {
        $normalized = mb_strtolower(trim((string) $status), 'UTF-8');
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
        $normalized = (string) preg_replace('/[^a-z0-9]/', '', $normalized);

        return in_array($normalized, ['perimee', 'perime', 'archivee', 'archive'], true);
    }

    #[Route('/{id}/check-tiime-status', name: 'check_tiime_status', methods: ['GET'])]
    public function checkTiimeStatus(Entetepiece $invoice): Response
    {
        $this->prepareInvoiceContext($invoice);
        $status = $this->invoiceService->checkTiimeStatus($invoice);

        return $this->json([
            'invoice_id' => $invoice->getId(),
            'tiime_status' => $status,
        ]);
    }

    #[Route('/{id}/history', name: 'history', methods: ['GET'])]
    public function viewHistory(Entetepiece $invoice): Response
    {
        $this->prepareInvoiceContext($invoice);
        $history = $this->invoiceService->getInvoiceHistory($invoice);

        return $this->json([
            'invoice_id' => $invoice->getId(),
            'lifecycle' => $history,
        ]);
    }

    #[Route('/{id}/download-pdf', name: 'download_pdf', methods: ['GET'])]
    public function downloadPdf(Request $request, Entetepiece $invoice): Response
    {
        $this->prepareInvoiceContext($invoice);
        $model = strtolower(trim((string) $request->query->get('model', '')));

        if ($model !== '') {
            $filename = $this->fileStorage->getPdfFilenameForModel($invoice, $model);
            if (!$this->fileStorage->fileExists($filename)) {
                throw $this->createNotFoundException(
                    'Ce modele PDF n est pas encore genere. Cliquez d abord sur Generer modele ' . ($model === 'modern' ? '2' : '1') . '.'
                );
            }

            $response = new Response($this->fileStorage->getFileContent($filename));
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="' . ($invoice->getPieceref() ?? ('invoice_' . $invoice->getId())) . '_' . $model . '.pdf"'
            );

            return $response;
        }

        if (!$invoice->getFactureXPdfFilename()) {
            throw $this->createNotFoundException('PDF file not found. Generate Facture-X first.');
        }

        try {
            $filepath = $this->fileStorage->getFilePath($invoice->getFactureXPdfFilename());
            if (!file_exists($filepath)) {
                throw $this->createNotFoundException('PDF file not found.');
            }

            $response = new Response(file_get_contents($filepath));
            $response->headers->set('Content-Type', 'application/pdf');
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="' . ($invoice->getPieceref() ?? ('invoice_' . $invoice->getId())) . '.pdf"'
            );

            return $response;
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Error downloading file: ' . $e->getMessage());
        }
    }

    #[Route('/{id}/download-xml', name: 'download_xml', methods: ['GET'])]
    public function downloadXml(Entetepiece $invoice): Response
    {
        $this->prepareInvoiceContext($invoice);
        if (!$invoice->getFactureXXmlFilename()) {
            throw $this->createNotFoundException('XML file not found. Generate Facture-X first.');
        }

        try {
            $filepath = $this->fileStorage->getFilePath($invoice->getFactureXXmlFilename());
            if (!file_exists($filepath)) {
                throw $this->createNotFoundException('XML file not found.');
            }

            $response = new Response(file_get_contents($filepath));
            $response->headers->set('Content-Type', 'application/xml');
            $response->headers->set(
                'Content-Disposition',
                'attachment; filename="' . ($invoice->getPieceref() ?? ('invoice_' . $invoice->getId())) . '.xml"'
            );

            return $response;
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Error downloading file: ' . $e->getMessage());
        }
    }

    #[Route('/webhook/tiime', name: 'tiime_webhook', methods: ['POST'])]
    public function tiimeWebhook(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true);

        if (isset($payload['invoice_id'])) {
            $invoice = $this->entityManager->getRepository(Entetepiece::class)->find($payload['invoice_id']);

            if ($invoice) {
                $this->invoiceService->updateInvoiceStatus(
                    $invoice,
                    'WEBHOOK_' . strtoupper($payload['status'] ?? 'UNKNOWN'),
                    $payload
                );
            }
        }

        return $this->json(['status' => 'received']);
    }

    private function prepareInvoiceContext(Entetepiece $invoice): void
    {
        $invoice->setDoctrine($this->doctrine);
        $invoice->setResolvedTierName($invoice->getTierName($this->doctrine));
    }
}

