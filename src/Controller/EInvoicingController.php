<?php

namespace App\Controller;

use App\Entity\Entetepiece;
use App\Service\EInvoicing\InvoiceService;
use App\Service\EInvoicing\FileStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/invoice/e-invoicing', name: 'invoice_einvoicing_')]
class EInvoicingController extends AbstractController
{
    public function __construct(
        private InvoiceService $invoiceService,
        private FileStorageService $fileStorage,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Test page for e-invoicing
     */
    #[Route('/{id}/test', name: 'test', methods: ['GET'])]
    public function testPage(Entetepiece $invoice): Response
    {
        return $this->render('e_invoicing/test.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    /**
     * Generate Facture-X for an invoice
     */
    #[Route('/{id}/generate-facturex', name: 'generate_facturex', methods: ['GET', 'POST'])]
    public function generateFactureX(Entetepiece $invoice): Response
    {
        try {
            $result = $this->invoiceService->generateFactureX($invoice);

            if ($result['success']) {
                // Mark invoice as Facture-X generated
                $invoice->setFactureX(true);
                $this->entityManager->flush();

                $this->addFlash('success', 'Facture-X generated successfully!');

                return $this->redirectToRoute('invoice_einvoicing_test', ['id' => $invoice->getId()]);
            } else {
                $this->addFlash('danger', 'Failed to generate Facture-X: ' . $result['error']);
                return $this->redirectToRoute('invoice_einvoicing_test', ['id' => $invoice->getId()]);
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Error: ' . $e->getMessage());
            return $this->redirectToRoute('invoice_einvoicing_test', ['id' => $invoice->getId()]);
        }
    }

    /**
     * Submit invoice to Tiime PDP
     */
    #[Route('/{id}/submit-tiime', name: 'submit_tiime', methods: ['GET', 'POST'])]
    public function submitToTiime(Entetepiece $invoice): Response
    {
        try {
            $result = $this->invoiceService->processInvoice($invoice);

            if ($result['success']) {
                $invoice->setSubmittedToTiime(true);
                $this->entityManager->flush();

                $this->addFlash('success', 'Invoice submitted to Tiime PDP successfully!');
                $this->addFlash('info', 'Tiime Invoice ID: ' . $result['tiime_invoice_id']);
                
                return $this->redirectToRoute('invoice_einvoicing_test', ['id' => $invoice->getId()]);
            } else {
                $this->addFlash('danger', 'Failed to submit invoice: ' . $result['error']);
                return $this->redirectToRoute('invoice_einvoicing_test', ['id' => $invoice->getId()]);
            }
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Error: ' . $e->getMessage());
            return $this->redirectToRoute('invoice_einvoicing_test', ['id' => $invoice->getId()]);
        }
    }

    /**
     * Check invoice status from Tiime
     */
    #[Route('/{id}/check-tiime-status', name: 'check_tiime_status', methods: ['GET'])]
    public function checkTiimeStatus(Entetepiece $invoice): Response
    {
        $status = $this->invoiceService->checkTiimeStatus($invoice);

        return $this->json([
            'invoice_id' => $invoice->getId(),
            'tiime_status' => $status,
        ]);
    }

    /**
     * View invoice lifecycle history
     */
    #[Route('/{id}/history', name: 'history', methods: ['GET'])]
    public function viewHistory(Entetepiece $invoice): Response
    {
        $history = $this->invoiceService->getInvoiceHistory($invoice);

        return $this->json([
            'invoice_id' => $invoice->getId(),
            'lifecycle' => $history,
        ]);
    }

    /**
     * Download Facture-X PDF
     */
    #[Route('/{id}/download-pdf', name: 'download_pdf', methods: ['GET'])]
    public function downloadPdf(Entetepiece $invoice): Response
    {
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
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $invoice->getPieceref() . '.pdf"');
            
            return $response;
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Error downloading file: ' . $e->getMessage());
        }
    }

    /**
     * Download Facture-X XML
     */
    #[Route('/{id}/download-xml', name: 'download_xml', methods: ['GET'])]
    public function downloadXml(Entetepiece $invoice): Response
    {
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
            $response->headers->set('Content-Disposition', 'attachment; filename="' . $invoice->getPieceref() . '.xml"');
            
            return $response;
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Error downloading file: ' . $e->getMessage());
        }
    }

    /**
     * Tiime Webhook endpoint (receives status updates)
     */
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
}
