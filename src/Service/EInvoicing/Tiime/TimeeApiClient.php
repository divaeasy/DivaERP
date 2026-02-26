<?php

namespace App\Service\EInvoicing\Tiime;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Handles communication with Tiime PDP (Plateforme De Portabilité) API
 */
class TimeeApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private string $partnerId;
    private string $environment;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        string $tiimeApiBaseUrl,
        string $tiimeApiKey,
        string $tiimeApiSecret,
        string $tiimePartnerId,
        string $tiimeEnvironment = 'sandbox',
    ) {
        $this->baseUrl = $tiimeApiBaseUrl;
        $this->apiKey = $tiimeApiKey;
        $this->apiSecret = $tiimeApiSecret;
        $this->partnerId = $tiimePartnerId;
        $this->environment = $tiimeEnvironment;
    }

    /**
     * Submit invoice to Tiime PDP
     *
     * @param string $invoiceXml - EN16931 XML content
     * @param string $invoicePdf - PDF content (base64 or binary)
     * @param string $invoiceNumber - Invoice number/reference
     * @param array $metadata - Additional metadata
     *
     * @return array Response from Tiime API
     */
    public function submitInvoice(
        string $invoiceXml,
        string $invoicePdf,
        string $invoiceNumber,
        array $metadata = []
    ): array {
        if (!$this->isConfigured()) {
            $this->logger->warning('Tiime API is not configured. Skipping submission.');
            return [
                'status' => 'skipped',
                'message' => 'Tiime API credentials not configured',
            ];
        }

        try {
            $payload = [
                'invoice_number' => $invoiceNumber,
                'invoice_xml' => base64_encode($invoiceXml),
                'invoice_pdf' => base64_encode($invoicePdf),
                'environment' => $this->environment,
                'partner_id' => $this->partnerId,
                'metadata' => $metadata,
            ];

            $response = $this->httpClient->request('POST', $this->baseUrl . '/api/v1/invoices/submit', [
                'json' => $payload,
                'headers' => $this->getAuthHeaders(),
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info('Invoice submitted to Tiime PDP', [
                    'invoice_number' => $invoiceNumber,
                    'response' => $content,
                ]);

                return [
                    'status' => 'submitted',
                    'tiime_invoice_id' => $content['invoice_id'] ?? null,
                    'submission_id' => $content['submission_id'] ?? null,
                    'response' => $content,
                ];
            } else {
                $this->logger->error('Failed to submit invoice to Tiime', [
                    'invoice_number' => $invoiceNumber,
                    'status_code' => $statusCode,
                    'response' => $content,
                ]);

                return [
                    'status' => 'failed',
                    'error' => $content['error'] ?? 'Unknown error',
                    'status_code' => $statusCode,
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception during Tiime API call', [
                'invoice_number' => $invoiceNumber,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get invoice status from Tiime
     */
    public function getInvoiceStatus(string $tiimeInvoiceId): array
    {
        if (!$this->isConfigured()) {
            return ['status' => 'unknown', 'message' => 'Not configured'];
        }

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/api/v1/invoices/' . $tiimeInvoiceId, [
                'headers' => $this->getAuthHeaders(),
                'timeout' => 10,
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error('Failed to get invoice status', [
                'tiime_invoice_id' => $tiimeInvoiceId,
                'exception' => $e->getMessage(),
            ]);

            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Webhook notification - Mark invoice as delivered/accepted
     */
    public function handleWebhookNotification(array $payload): void
    {
        $invoiceId = $payload['invoice_id'] ?? null;
        $status = $payload['status'] ?? null;
        $rejectionReason = $payload['rejection_reason'] ?? null;

        $this->logger->info('Tiime webhook received', [
            'invoice_id' => $invoiceId,
            'status' => $status,
            'rejection_reason' => $rejectionReason,
        ]);

        // TODO: Update invoice status in database based on webhook data
        // This will be handled by InvoiceService
    }

    /**
     * Check if API is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret) && !empty($this->partnerId);
    }

    /**
     * Generate authentication headers for API requests
     */
    private function getAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-Partner-Id' => $this->partnerId,
            'X-Api-Secret' => $this->apiSecret,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
