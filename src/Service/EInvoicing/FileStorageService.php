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
    public function savePdf(Entetepiece $invoice, string $pdfContent): string
    {
        $this->initialize();
        $filename = $this->getFileName($invoice, 'pdf');
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
        $filename = $this->getFileName($invoice, 'xml');
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
     * Generate filename for invoice
     */
    private function getFileName(Entetepiece $invoice, string $extension): string
    {
        $invoiceRef = $invoice->getPieceref() ?? 'invoice_' . $invoice->getId();
        $timestamp = time();
        return sprintf('%d_%s.%s', $invoice->getId(), preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoiceRef), $extension);
    }

    /**
     * Get storage directory path
     */
    public function getStoragePath(): string
    {
        return $this->storagePath;
    }
}
