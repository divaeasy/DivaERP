<?php

namespace App\Service\EInvoicing;

use App\Entity\Entetepiece;
use App\Entity\InvoiceStatus;
use App\Service\EInvoicing\EN16931\EN16931Builder;
use App\Service\EInvoicing\FactureX\FactureXGenerator;
use App\Service\EInvoicing\Tiime\TimeeApiClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use DateTime;

/**
 * Orchestrates the e-invoicing workflow
 * Handles: XML generation → PDF creation → Tiime submission → Status tracking
 */
class InvoiceService
{
    public function __construct(
        private EN16931Builder $xmlBuilder,
        private FactureXGenerator $pdfGenerator,
        private TimeeApiClient $tiimeClient,
        private FileStorageService $fileStorage,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Generate complete Facture-X invoice
     *
     * @return array PDF content and XML content
     */
    public function generateFactureX(Entetepiece $invoice): array
    {
        try {
            $xmlContent = $this->xmlBuilder->buildInvoiceXml($invoice);
            $pdfContent = $this->pdfGenerator->generateFactureX($invoice);

            // Save files to disk
            $pdfFilename = $this->fileStorage->savePdf($invoice, $pdfContent);
            $xmlFilename = $this->fileStorage->saveXml($invoice, $xmlContent);

            // Store filenames in entity
            $invoice->setFactureXPdfFilename($pdfFilename);
            $invoice->setFactureXXmlFilename($xmlFilename);
            $this->entityManager->flush();

            $this->logger->info('Facture-X generated successfully', [
                'invoice_id' => $invoice->getId(),
                'invoice_ref' => $invoice->getPieceref(),
                'pdf_filename' => $pdfFilename,
                'xml_filename' => $xmlFilename,
            ]);

            return [
                'success' => true,
                'xml' => $xmlContent,
                'pdf' => $pdfContent,
                'pdf_filename' => $pdfFilename,
                'xml_filename' => $xmlFilename,
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
                'timestamp' => $status->getCreatedAt()->format('Y-m-d H:i:s'),
                'details' => $status->getDetails(),
            ];
        }, $statuses);
    }
}
