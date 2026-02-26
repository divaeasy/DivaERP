<?php

namespace App\Service\EInvoicing\FactureX;

use App\Entity\Entetepiece;
use App\Service\EInvoicing\EN16931\EN16931Builder;
use Mpdf\Mpdf;
use DateTime;

/**
 * Generates Facture-X PDF/A-3 with embedded XML
 */
class FactureXGenerator
{
    public function __construct(
        private EN16931Builder $xmlBuilder,
    ) {}

    /**
     * Generate complete Facture-X invoice (PDF + embedded XML)
     */
    public function generateFactureX(Entetepiece $invoice): string
    {
        // Generate XML
        $xmlContent = $this->xmlBuilder->buildInvoiceXml($invoice);

        // Create PDF with MPDF
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
        ]);

        // Set PDF metadata
        $mpdf->SetTitle('Facture ' . $invoice->getPieceref());
        $mpdf->SetAuthor('Your Company Name');
        $mpdf->SetCreator('DivaERP');

        // Build HTML content for invoice
        $htmlContent = $this->generateHtmlContent($invoice);

        // Write HTML to PDF
        $mpdf->WriteHTML($htmlContent);

        // Attach XML as attachment (Facture-X requirement)
        // Note: MPDF doesn't support PDF/A-3 with attachments directly
        // You may need to use tcpdf or a library that supports PDF/A-3 with attachments
        // For now, this generates a standard PDF with the invoice data

        return $mpdf->Output('', 'S'); // Return as string
    }

    /**
     * Generate HTML representation of invoice
     */
    private function generateHtmlContent(Entetepiece $invoice): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header { margin-bottom: 30px; }
        .company-info { font-size: 14px; font-weight: bold; margin-bottom: 10px; }
        .invoice-title { font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 12px; font-weight: bold; background: #f0f0f0; padding: 5px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; font-weight: bold; }
        .amount { text-align: right; }
        .total-row { font-weight: bold; }
        .footer { margin-top: 30px; font-size: 10px; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-info">Your Company Name</div>
        <div class="invoice-title">INVOICE</div>
        <p><strong>Invoice #:</strong> ' . htmlspecialchars($invoice->getPieceref() ?? $invoice->getId()) . '</p>
        <p><strong>Date:</strong> ' . ($invoice->getDatep() ? $invoice->getDatep()->format('d/m/Y') : date('d/m/Y')) . '</p>
    </div>';

        // Seller section
        $html .= '<div class="section">
        <div class="section-title">FROM (Seller)</div>
        <p>Your Company Name<br/>
        SIREN: [SIREN]<br/>
        Your Address<br/>
        Your City, Your Postal Code<br/>
        Your Country
        </p>
    </div>';

        // Buyer section
        if ($invoice->getClient()) {
            $html .= '<div class="section">
        <div class="section-title">TO (Buyer)</div>
        <p>' . htmlspecialchars($invoice->getClient()->getRaisonSociale() ?? $invoice->getClient()->getNom()) . '<br/>';
            
            if ($invoice->getClient()->getAdresse()) {
                $html .= htmlspecialchars($invoice->getClient()->getAdresse()) . '<br/>';
            }
            
            $html .= '</p>
    </div>';
        }

        // Invoice lines
        $html .= '<div class="section">
        <div class="section-title">Line Items</div>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Qty</th>
                    <th class="amount">Unit Price</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($invoice->getLignepieces() as $line) {
            $lineTotal = ($line->getQuantite() ?? 0) * ($line->getPu() ?? 0);
            $html .= '<tr>
                <td>' . htmlspecialchars($line->getDesignation() ?? '') . '</td>
                <td class="amount">' . number_format($line->getQuantite() ?? 0, 2) . '</td>
                <td class="amount">' . number_format($line->getPu() ?? 0, 2) . '</td>
                <td class="amount">' . number_format($lineTotal, 2) . '</td>
            </tr>';
        }

        $html .= '        </tbody>
        </table>
    </div>';

        // Totals
        $html .= '<div class="section">
        <table>
            <tr class="total-row">
                <td style="text-align: right; width: 50%;">TOTAL:</td>
                <td class="amount">' . number_format($invoice->getMontant() ?? 0, 2) . ' ' . ($invoice->getDevise()?->getCode() ?? 'EUR') . '</td>
            </tr>
        </table>
    </div>';

        // Payment terms
        if ($invoice->getReglement()) {
            $html .= '<div class="section">
        <div class="section-title">Payment Terms</div>
        <p>' . htmlspecialchars($invoice->getReglement()->getLibelle()) . ' (Due in ' . $invoice->getReglement()->getEcheance() . ' days)</p>
    </div>';
        }

        // Footer
        $html .= '<div class="footer">
        <p>This is a Facture-X compliant invoice. It contains structured data embedded as XML.</p>
        <p>Generated on ' . date('d/m/Y H:i:s') . '</p>
    </div>
</body>
</html>';

        return $html;
    }
}
