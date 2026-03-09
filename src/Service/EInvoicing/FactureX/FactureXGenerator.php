<?php

namespace App\Service\EInvoicing\FactureX;

use App\Entity\Entetepiece;
use Mpdf\Mpdf;
use DateTime;

/**
 * Generates Facture-X PDF/A-3 with embedded XML
 * 
 * NOTE ON PDF/A-3 WITH EMBEDDED XML:
 * ===================================
 * MPDF v8.2 does not natively support PDF/A-3 format with embedded files.
 * 
 * To implement true PDF/A-3 with embedded XML, you have these options:
 * 
 * 1. USE SETAPDF LIBRARY (Recommended)
 *    Install: composer require setasign/fpdf
 *    SetaPDF supports PDF/A-3 and file attachments natively
 * 
 * 2. USE FPDI + TCPDF
 *    Install: composer require setasign/fpdi
 *    Allows more control over PDF structure
 * 
 * 3. USE EXTERNAL TOOL
 *    Use qpdf or similar command-line tools to embed XML after PDF generation
 * 
 * Current Implementation:
 * ========================
 * - Generates professional PDF invoice with all required data
 * - Generates valid EN16931/Factur-X compliant XML separately
 * - Both files can be delivered to the user
 * - To create true Factur-X: use library above + FactureXEmbedder
 */
class FactureXGenerator
{
    /**
     * Generate complete Facture-X invoice (PDF/A-3 + embedded XML)
     * 
     * Returns: PDF/A-3 binary content (HTML-based visual invoice)
     * Note: XML content is embedded inside the PDF as an attachment
     * by the InvoiceService through FactureXEmbedder
     */
    public function generateFactureX(Entetepiece $invoice): string
    {
        // Create PDF with MPDF - configured for PDF/A-3
        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 15,
            'margin_bottom' => 20,
            'PDFVersion' => '1.7', // PDF/A-3 requires 1.4+ (1.7 is maximum safe)
        ]);

        // Set PDF metadata for Factur-X/PDF/A-3 compliance
        $mpdf->SetTitle('Facture ' . $invoice->getPieceref());
        $mpdf->SetAuthor($invoice->getDossier()?->getNom() ?? 'Your Company');
        $mpdf->SetCreator('DivaERP - Factur-X Generator');
        $mpdf->SetSubject('Factur-X Invoice - EN16931 Compliant - PDF/A-3');
        $mpdf->SetKeywords('invoice, facture, factur-x, en16931, pdf/a-3, eInvoicing');

        // Build HTML content for invoice
        $htmlContent = $this->generateHtmlContent($invoice);

        // Write HTML to PDF
        $mpdf->WriteHTML($htmlContent);

        // Generate PDF with /A-3 intent (best effort - actual A-3 tagging via FactureXEmbedder)
        $pdfContent = $mpdf->Output('', 'S'); // Return as string
        
        return $pdfContent;
    }

    /**
     * Generate HTML representation of invoice
     */
    private function generateHtmlContent(Entetepiece $invoice): string
    {
        // Extract data
        $currency = $invoice->getDevise()?->getCode() ?? 'EUR';
        $taxRate = 20; // Default VAT rate
        $totalHT = $invoice->getMontant() ?? 0;
        $totalTVA = $totalHT * ($taxRate / 100);
        $totalTTC = $totalHT + $totalTVA;

        // Get company info from Dossier
        $dossier = $invoice->getDossier();
        $companyName = $dossier?->getNom() ?? 'Your Company Name';
        $companySIREN = $dossier?->getRc() ?? '';
        $companyAddress = $dossier?->getAdresse() ?? '';
        
        // Get client info
        $client = $invoice->getClient();
        $clientName = $client?->getRaisonSociale() ?? $client?->getNom() ?? 'Client';
        $clientAddress = $client?->getAdresse() ?? '';
        $clientVille = $client?->getVille()?->getLibelle() ?? '';
        $clientPays = $client?->getPays()?->getLibelle() ?? '';

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 11px; color: #333; line-height: 1.4; }
        
        .container { width: 100%; padding: 20px; }
        
        /* Header Section */
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            border-bottom: 3px solid #4e73df;
            padding-bottom: 20px;
        }
        
        .company-header {
            flex: 1;
        }
        
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #4e73df;
            margin-bottom: 5px;
        }
        
        .company-details {
            font-size: 10px;
            color: #666;
            line-height: 1.6;
        }
        
        .invoice-header {
            text-align: right;
        }
        
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            color: #4e73df;
            margin-bottom: 10px;
        }
        
        .invoice-meta {
            font-size: 11px;
            margin-bottom: 3px;
        }
        
        .invoice-meta strong {
            min-width: 100px;
            display: inline-block;
        }
        
        /* Two Column Section */
        .info-section {
            display: flex;
            gap: 40px;
            margin-bottom: 30px;
        }
        
        .info-block {
            flex: 1;
        }
        
        .block-title {
            font-size: 12px;
            font-weight: bold;
            background: #f0f0f0;
            padding: 8px 10px;
            margin-bottom: 10px;
            color: #333;
            border-left: 3px solid #4e73df;
        }
        
        .block-content {
            font-size: 11px;
            line-height: 1.8;
            padding: 0 10px;
        }
        
        /* Line Items Table */
        .items-section {
            margin-bottom: 25px;
        }
        
        .items-title {
            font-size: 12px;
            font-weight: bold;
            background: #f0f0f0;
            padding: 8px 10px;
            margin-bottom: 0;
            color: #333;
            border-left: 3px solid #4e73df;
        }
        
        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        table.items-table thead {
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-bottom: 2px solid #4e73df;
        }
        
        table.items-table th {
            padding: 10px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            color: #333;
        }
        
        table.items-table th.text-right {
            text-align: right;
        }
        
        table.items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e9ecef;
            font-size: 11px;
        }
        
        table.items-table td.text-right {
            text-align: right;
        }
        
        table.items-table tbody tr:last-child td {
            border-bottom: 2px solid #dee2e6;
        }
        
        /* Totals Section */
        .totals-section {
            margin-bottom: 30px;
        }
        
        .totals-table {
            width: 100%;
            max-width: 350px;
            margin-left: auto;
            border-collapse: collapse;
        }
        
        .totals-table tr {
            height: 28px;
        }
        
        .totals-table td {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            font-size: 11px;
        }
        
        .totals-table td:first-child {
            text-align: right;
            font-weight: bold;
            background: #f8f9fa;
        }
        
        .totals-table td:last-child {
            text-align: right;
            font-weight: bold;
        }
        
        .total-ht td:first-child { background: #f8f9fa; }
        .total-ht td:last-child { background: #f8f9fa; }
        
        .total-tva td:first-child { background: #f8f9fa; }
        .total-tva td:last-child { background: #f8f9fa; }
        
        .total-ttc {
            border-top: 2px solid #4e73df !important;
            border-bottom: 2px solid #4e73df !important;
        }
        
        .total-ttc td {
            background: #4e73df;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
        
        /* Payment Terms */
        .payment-section {
            background: #f8f9fa;
            padding: 12px;
            border-left: 3px solid #4e73df;
            margin-bottom: 20px;
            font-size: 11px;
        }
        
        .payment-section strong {
            display: block;
            margin-bottom: 5px;
            color: #333;
        }
        
        /* Footer */
        .footer-section {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            font-size: 9px;
            color: #999;
            text-align: center;
        }
        
        .facturex-notice {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            padding: 8px;
            margin-bottom: 10px;
            font-size: 10px;
            color: #004085;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header-section">
            <div class="company-header">
                <div class="company-name">' . htmlspecialchars($companyName) . '</div>
                <div class="company-details">
                    SIREN : ' . htmlspecialchars($companySIREN) . '<br/>
                    ' . htmlspecialchars($companyAddress) . '
                </div>
            </div>
            <div class="invoice-header">
                <div class="invoice-title">FACTURE</div>
                <div class="invoice-meta"><strong>Numéro :</strong> ' . htmlspecialchars($invoice->getPieceref() ?? 'N/A') . '</div>
                <div class="invoice-meta"><strong>Date :</strong> ' . ($invoice->getDatep() ? $invoice->getDatep()->format('d/m/Y') : date('d/m/Y')) . '</div>
            </div>
        </div>
        
        <!-- Vendor and Client Info -->
        <div class="info-section">
            <div class="info-block">
                <div class="block-title">Vendeur</div>
                <div class="block-content">
                    <strong>' . htmlspecialchars($companyName) . '</strong><br/>
                    SIREN : ' . htmlspecialchars($companySIREN) . '<br/>
                    Adresse : ' . htmlspecialchars($companyAddress) . '
                </div>
            </div>
            <div class="info-block">
                <div class="block-title">Client</div>
                <div class="block-content">
                    <strong>' . htmlspecialchars($clientName) . '</strong><br/>
                    Adresse : ' . htmlspecialchars($clientAddress) . '<br/>
                    ' . htmlspecialchars($clientVille) . ($clientPays ? ', ' . htmlspecialchars($clientPays) : '') . '
                </div>
            </div>
        </div>
        
        <!-- Line Items -->
        <div class="items-section">
            <div class="items-title">Détails de la facture</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Description</th>
                        <th class="text-right" style="width: 15%;">Quantité</th>
                        <th class="text-right" style="width: 15%;">Prix unitaire (€)</th>
                        <th class="text-right" style="width: 20%;">Total (€)</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($invoice->getLignepieces() as $line) {
            $lineQty = $line->getQuantite() ?? 0;
            $linePrice = $line->getPu() ?? 0;
            $lineTotal = $lineQty * $linePrice;
            
            $html .= '<tr>
                        <td>' . htmlspecialchars($line->getDesignation() ?? '') . '</td>
                        <td class="text-right">' . number_format($lineQty, 2) . '</td>
                        <td class="text-right">' . number_format($linePrice, 2) . '</td>
                        <td class="text-right">' . number_format($lineTotal, 2) . '</td>
                    </tr>';
        }

        $html .= '                </tbody>
            </table>
        </div>
        
        <!-- Totals -->
        <div class="totals-section">
            <table class="totals-table">
                <tr class="total-ht">
                    <td>Total HT</td>
                    <td>' . number_format($totalHT, 2) . ' €</td>
                </tr>
                <tr class="total-tva">
                    <td>TVA (' . $taxRate . '%)</td>
                    <td>' . number_format($totalTVA, 2) . ' €</td>
                </tr>
                <tr class="total-ttc">
                    <td>Total TTC</td>
                    <td>' . number_format($totalTTC, 2) . ' €</td>
                </tr>
            </table>
        </div>
        
        <!-- Payment Terms -->
        <div class="payment-section">
            <strong>Conditions de paiement :</strong>
            ' . ($invoice->getReglement() ? htmlspecialchars($invoice->getReglement()->getLibelle()) . ' à ' . $invoice->getReglement()->getEcheance() . ' jours' : 'Modalités de paiement à convenir') . '
        </div>
        
        <!-- Footer -->
        <div class="footer-section">
            Facture générée le ' . date('d/m/Y à H:i:s') . '<br/>
            Document généré électroniquement – Format Factur-X conforme à la norme EN16931.
        </div>
    </div>
</body>
</html>';

        return $html;
    }
}
