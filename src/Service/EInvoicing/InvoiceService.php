<?php

namespace App\Service\EInvoicing;

use App\Entity\Entetepiece;
use App\Entity\InvoiceStatus;
use App\Service\EInvoicing\EN16931\FactureXBuilder;
use App\Service\EInvoicing\FactureX\FactureXGenerator;
use App\Service\EInvoicing\FactureX\FactureXEmbedder;
use App\Service\EInvoicing\Tiime\TimeeApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use DateTime;

/**
 * Orchestrates the e-invoicing workflow
 * Handles: XML generation → PDF creation → XML embedding → Tiime submission → Status tracking
 */
class InvoiceService
{
    public function __construct(
        private FactureXBuilder $xmlBuilder,
        private FactureXGenerator $pdfGenerator,
        private FactureXEmbedder $embedder,
        private TimeeApiClient $tiimeClient,
        private FileStorageService $fileStorage,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Generate complete Facture-X invoice with embedded XML
     *
     * @return array PDF content (with embedded XML)
     */
    public function generateFactureX(Entetepiece $invoice): array
    {
        try {
            $xmlContent = $this->xmlBuilder->buildInvoiceXml($invoice);
            $pdfContent = $this->pdfGenerator->generateFactureX($invoice);

            // Embed XML into PDF to create true Factur-X document
            $pdfWithEmbeddedXml = $this->embedXmlInPdf($pdfContent, $xmlContent, $invoice->getPieceref() ?? 'invoice');

            // Save only the PDF with embedded XML (no separate XML file)
            $pdfFilename = $this->fileStorage->savePdf($invoice, $pdfWithEmbeddedXml);

            // Store filename in entity (only PDF, XML is embedded)
            $invoice->setFactureXPdfFilename($pdfFilename);
            $invoice->setFactureXXmlFilename(null); // No separate XML file
            $this->entityManager->flush();

            $this->logger->info('Facture-X generated successfully with embedded XML', [
                'invoice_id' => $invoice->getId(),
                'invoice_ref' => $invoice->getPieceref(),
                'pdf_filename' => $pdfFilename,
            ]);

            return [
                'success' => true,
                'xml' => $xmlContent, // For reference only
                'pdf' => $pdfWithEmbeddedXml,
                'pdf_filename' => $pdfFilename,
                'xml_filename' => null, // No separate XML file
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate Facture-X', [
                'invoice_id' => $invoice->getId(),
                'exception' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Embed XML into PDF using available method
     * Tries raw PDF manipulation first, then fallback to separate files
     */
    /**
     * Embed XML into PDF - delegates to embedder which handles all strategies
     */
    private function embedXmlInPdf(string $pdfContent, string $xmlContent, string $invoiceRef): string
    {
        // The embedder now handles all embedding logic internally with fallback strategies
        return $this->embedder->embedXmlInPdf($pdfContent, $xmlContent, $invoiceRef);
    }


    /**
     * Submit invoice to Tiime PDP
     */
    public function submitToTiime(Entetepiece $invoice, string $xmlContent, string $pdfContent): array
    {
        try {
            $metadata = [
                'invoice_date' => $invoice->getDatep()?->format('Y-m-d'),
                'buyer_name' => $invoice->getClient()?->getRaisonSociale() ?? $invoice->getClient()?->getNom(),
                'invoice_amount' => $invoice->getMontant(),
                'currency' => $invoice->getDevise()?->getCode() ?? 'EUR',
            ];

            $result = $this->tiimeClient->submitInvoice(
                $xmlContent,
                $pdfContent,
                (string) $invoice->getPieceref(),
                $metadata
            );

            // Update invoice status
            $this->updateInvoiceStatus($invoice, 'SENT_TO_PDP', $result);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to submit to Tiime', [
                'invoice_id' => $invoice->getId(),
                'exception' => $e->getMessage(),
            ]);

            $this->updateInvoiceStatus($invoice, 'SUBMISSION_FAILED', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Complete workflow: generate + submit
     */
    public function processInvoice(Entetepiece $invoice): array
    {
        // Step 1: Generate Facture-X
        $factureX = $this->generateFactureX($invoice);

        if (!$factureX['success']) {
            $this->updateInvoiceStatus($invoice, 'GENERATION_FAILED', [
                'error' => $factureX['error'],
            ]);

            return [
                'success' => false,
                'phase' => 'generation',
                'error' => $factureX['error'],
            ];
        }

        // Step 2: Submit to Tiime
        $submission = $this->submitToTiime($invoice, $factureX['xml'], $factureX['pdf']);

        if ($submission['status'] === 'submitted') {
            // Store Tiime invoice ID
            $invoice->setTiimeInvoiceId($submission['tiime_invoice_id'] ?? null);
            $invoice->setTiimeSubmissionId($submission['submission_id'] ?? null);
            $this->entityManager->flush();

            return [
                'success' => true,
                'message' => 'Invoice processed successfully',
                'tiime_invoice_id' => $submission['tiime_invoice_id'],
                'submission_id' => $submission['submission_id'],
            ];
        } else {
            return [
                'success' => false,
                'phase' => 'submission',
                'error' => $submission['error'] ?? 'Submission failed',
            ];
        }
    }

    /**
     * Update invoice status in database
     */
    public function updateInvoiceStatus(
        Entetepiece $invoice,
        string $status,
        array $details = []
    ): InvoiceStatus {
        $invoiceStatus = new InvoiceStatus();
        $invoiceStatus->setInvoice($invoice);
        $invoiceStatus->setStatus($status);
        $invoiceStatus->setDetails($details);
        $invoiceStatus->setCreatedAt(new DateTime());

        $this->entityManager->persist($invoiceStatus);
        $this->entityManager->flush();

        $this->logger->info('Invoice status updated', [
            'invoice_id' => $invoice->getId(),
            'status' => $status,
        ]);

        return $invoiceStatus;
    }

    /**
     * Check current status from Tiime
     */
    public function checkTiimeStatus(Entetepiece $invoice): array
    {
        if (!$invoice->getTiimeInvoiceId()) {
            return [
                'error' => 'No Tiime invoice ID',
            ];
        }

        $status = $this->tiimeClient->getInvoiceStatus($invoice->getTiimeInvoiceId());

        // Update local status based on Tiime response
        if (isset($status['status'])) {
            $this->updateInvoiceStatus($invoice, 'TIIME_' . strtoupper($status['status']), $status);
        }

        return $status;
    }

    /**
     * Get invoice lifecycle history
     */
    public function getInvoiceHistory(Entetepiece $invoice): array
    {
        $statuses = $this->entityManager
            ->getRepository(InvoiceStatus::class)
            ->findBy(['invoice' => $invoice], ['createdAt' => 'ASC']);

        return array_map(function (InvoiceStatus $status) {
            return [
                'status' => $status->getStatus(),
                'created_at' => $status->getCreatedAt()->format('d/m/Y H:i:s'),
                'details' => $status->getDetails(),
            ];
        }, $statuses);
    }
}
