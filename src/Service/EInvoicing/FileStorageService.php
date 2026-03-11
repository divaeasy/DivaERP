<?php

namespace App\Service\EInvoicing;

use App\Entity\Entetepiece;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Handles storage of generated e-invoicing files (PDF, XML)
 */
class FileStorageService
{
    private readonly string $storagePath;

    public function __construct(
        private readonly string $projectDir,
    ) {
        $this->storagePath = $this->projectDir . '/var/e-invoicing';
    }

    /**
     * Initialize storage directory
     */
    public function initialize(): void
    {
        $fs = new Filesystem();
        if (!$fs->exists($this->storagePath)) {
            $fs->mkdir($this->storagePath);
        }
    }

    /**
     * Save PDF file
     */
    public function savePdf(Entetepiece $invoice, string $pdfContent, ?string $model = null): string
    {
        $this->initialize();
        $filename = $this->getFileName($invoice, 'pdf', $model);
        $filepath = $this->storagePath . '/' . $filename;

        file_put_contents($filepath, $pdfContent);

        return $filename;
    }

    /**
     * Save XML file
     */
    public function saveXml(Entetepiece $invoice, string $xmlContent): string
    {
        $this->initialize();
        $filename = $this->getFileName($invoice, 'xml', null);
        $filepath = $this->storagePath . '/' . $filename;

        file_put_contents($filepath, $xmlContent);

        return $filename;
    }

    /**
     * Get file path
     */
    public function getFilePath(string $filename): string
    {
        return $this->storagePath . '/' . $filename;
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $filename): bool
    {
        return file_exists($this->getFilePath($filename));
    }

    /**
     * Get file content
     */
    public function getFileContent(string $filename): string
    {
        $filepath = $this->getFilePath($filename);
        if (!file_exists($filepath)) {
            throw new \RuntimeException("File not found: {$filename}");
        }
        return file_get_contents($filepath);
    }

    /**
     * Delete file
     */
    public function deleteFile(string $filename): void
    {
        $filepath = $this->getFilePath($filename);
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    /**
     * Return the deterministic PDF filename for a specific visual model.
     */
    public function getPdfFilenameForModel(Entetepiece $invoice, string $model): string
    {
        return $this->getFileName($invoice, 'pdf', $model);
    }

    /**
     * Generate filename for invoice
     */
    private function getFileName(Entetepiece $invoice, string $extension, ?string $variant = null): string
    {
        $invoiceRef = $invoice->getPieceref() ?? 'invoice_' . $invoice->getId();
        $safeRef = preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoiceRef);
        $safeVariant = trim((string) preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $variant), '_');

        if ($safeVariant !== '' && $extension === 'pdf') {
            return sprintf('%d_%s_%s.%s', $invoice->getId(), $safeRef, strtolower($safeVariant), $extension);
        }

        return sprintf('%d_%s.%s', $invoice->getId(), $safeRef, $extension);
    }

    /**
     * Get storage directory path
     */
    public function getStoragePath(): string
    {
        return $this->storagePath;
    }
}
