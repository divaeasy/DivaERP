<?php

namespace App\Service\EInvoicing\FactureX;

use App\Entity\Entetepiece;
use Mpdf\Mpdf;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class FactureXGenerator
{
    public const MODEL_CLASSIC = 'classic';
    public const MODEL_MODERN = 'modern';
    public const DEFAULT_MODEL = self::MODEL_CLASSIC;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    /**
     * Generate Facture-X visual PDF (XML embedding is handled by FactureXEmbedder).
     */
    public function generateFactureX(Entetepiece $invoice, string $model = self::DEFAULT_MODEL): string
    {
        $resolvedModel = $this->normalizeModel($model);

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 30,
            'PDFVersion' => '1.7',
        ]);

        $mpdf->SetTitle('Facture ' . ($invoice->getPieceref() ?? (string) $invoice->getId()));
        $mpdf->SetAuthor($invoice->getDossier()?->getNom() ?? 'DivaERP');
        $mpdf->SetCreator('DivaERP - Factur-X Generator');
        $mpdf->SetSubject('Factur-X Invoice - EN16931');
        $mpdf->SetKeywords('invoice,facture,factur-x,en16931,pdf');

        // Generate and set fixed footer using mPDF's native footer functionality
        $footerHtml = $this->generateFooterHtml($invoice, $resolvedModel);
        $mpdf->SetHTMLFooter($footerHtml);

        // Generate main content (with payment/bank info, without company footer)
        $mpdf->WriteHTML($this->generateHtmlContent($invoice, $resolvedModel, true));

        return $mpdf->Output('', 'S');
    }

    /**
     * @return array<string, string>
     */
    public function getAvailableModels(): array
    {
        return [
            self::MODEL_CLASSIC => 'Modele 1 - Classique',
            self::MODEL_MODERN => 'Modele 2 - Moderne',
        ];
    }

    private function normalizeModel(string $model): string
    {
        $model = strtolower(trim($model));

        return in_array($model, [self::MODEL_CLASSIC, self::MODEL_MODERN], true)
            ? $model
            : self::DEFAULT_MODEL;
    }

    private function generateHtmlContent(Entetepiece $invoice, string $model, bool $includeFooter = true): string
    {
        $data = $this->buildInvoiceData($invoice);

        if ($model === self::MODEL_MODERN) {
            return $this->buildModernHtml($data, $includeFooter);
        }

        return $this->buildClassicHtml($data, $includeFooter);
    }

    /**
     * Generate footer HTML for mPDF's SetHTMLFooter() method.
     */
    private function generateFooterHtml(Entetepiece $invoice, string $model): string
    {
        $data = $this->buildInvoiceData($invoice);
        $buyerCityLine = trim($data['buyer_postcode'] . ' ' . $data['buyer_city']);

        if ($model === self::MODEL_MODERN) {
            return '<div style="width: 100%; font-size: 11px; color: #5a6d81; line-height: 1.5; margin-bottom: 15px;">
<strong>Conditions de paiement:</strong> ' . $this->e($data['payment_text'] !== '' ? $data['payment_text'] : 'paiement à réception de facture') . '<br>
<strong>Coordonnées bancaires:</strong><br>
IBAN: ' . ($data['bank_iban'] !== '' ? $this->e($data['bank_iban']) : '') . '<br>
Code SWIFT: ' . ($data['bank_bic'] !== '' ? $this->e($data['bank_bic']) : '') . '<br>
</div>

<div style="height: 12px;"></div>

<div style="width: 100%; border-top: 1px solid #d7e3ef; border-collapse: collapse; padding-top: 8px;">
<table style="width: 100%; border-collapse: collapse; font-size: 11.4px; color: #62798f;">
<tr>
<td style="width: 33.33%; text-align: center; padding: 8px 5px;">ICE ' . $this->e($data['seller_ice']) . '</td>
<td style="width: 33.33%; text-align: center; padding: 8px 5px; border-right: 1px solid #d7e3ef; border-left: 1px solid #d7e3ef;">RC ' . $this->e($data['seller_rc']) . '</td>
<td style="width: 33.33%; text-align: center; padding: 8px 5px;">SC: ' . $this->e($data['seller_sc']) . '</td>
</tr>
<tr>
<td colspan="3" style="text-align: center; padding-top: 2px; font-size: 8.4px;">Email ' . $this->e($data['seller_email'] !== '' ? $data['seller_email'] : '-') . '</td>
</tr>
</table>
</div>';
        }

        // Classic model footer
        return '
<div class="footer">
<div class="footer-box">

<table class="footer-table">

<tr class="footer-head">
<td colspan="3">'.$this->e($data['seller_name']).'</td>
</tr>

<tr>
<td>Siret : '.$this->e($data['seller_ice']).'</td>
<td>Code NAF : '.$this->e($data['seller_naf']).'</td>
<td>Email : '.$this->e($data['seller_email']).'</td>
</tr>

<tr>
<td>TVA Intra : '.$this->e($data['seller_vat_number']).'</td>
<td>Tel : '.$this->e($data['seller_phone'] ?? '').'</td>
<td>'.$this->e($data['seller_rc']).'</td>
</tr>

</table>

</div>
</div>';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInvoiceData(Entetepiece $invoice): array
    {
        $currency = strtoupper((string) ($invoice->getDevise()?->getCode() ?? 'EUR'));
        $taxRateRaw = $invoice->getTaxRate();
        if ($taxRateRaw === null || trim((string) $taxRateRaw) === '') {
            $taxRateRaw = $invoice->getRemise();
        }
        $taxRate = (float) ($taxRateRaw ?? 0.0);
        $taxRate = $this->normalizeVatRate($taxRate);

        $dossier = $invoice->getDossier();
        $client = $invoice->getClient();

        $invoiceRef = (string) ($invoice->getPieceref() ?? ('INV-' . $invoice->getId()));
        $invoiceDate = $invoice->getDatep()?->format('d/m/Y') ?? date('d/m/Y');
        $dueDate = $invoice->getDelai()?->format('d/m/Y') ?? '-';

        $sellerAddressRaw = (string) ($dossier?->getAdresse() ?? '');
        $sellerAddressLines = $this->compactLines(preg_split('/[\r\n,]+/', $sellerAddressRaw) ?: []);
        $sellerPostalCity = trim((string) ($dossier?->getCodepostal() ?? '') . ' ' . (string) ($dossier?->getVille() ?? ''));
        if ($sellerPostalCity !== '') {
            $sellerAddressLines[] = $sellerPostalCity;
        }
        $sellerCountry = trim((string) ($dossier?->getPays() ?? ''));
        if ($sellerCountry === '') {
            $sellerCountry = $this->extractCountry($sellerAddressRaw, 'France');
        }
        $sellerAddressLines = $this->compactLines($sellerAddressLines);

        $buyerAddressLines = $this->compactLines([
            (string) ($client?->getAdr1() ?? ''),
            (string) ($client?->getAdr2() ?? ''),
            (string) ($client?->getRue() ?? ''),
        ]);

        if ($buyerAddressLines === []) {
            $buyerAddressLines = $this->compactLines(preg_split('/[\r\n,]+/', (string) ($client?->getAdresse() ?? '')) ?: []);
        }

        $buyerPostcode = trim((string) ($client?->getCodepostal() ?? ''));
        $buyerCity = trim((string) ($client?->getVille()?->getLibelle() ?? ''));
        $buyerCountry = trim((string) ($client?->getPays()?->getLibelle() ?? ''));
        if ($buyerCountry === '') {
            $buyerCountry = 'France';
        }

        $buyerCode = '';
        if ($client?->getId() !== null) {
            $buyerCode = sprintf('C%06d', $client->getId());
        }

        $lineItems = [];
        $computedTotalHt = 0.0;
        $lineIndex = 1;
        foreach ($invoice->getLignepieces() as $line) {
            $qty = (float) ($line->getQuantite() ?? 0.0);
            $unitPrice = (float) ($line->getPu() ?? 0.0);
            $lineTotal = round($qty * $unitPrice, 2);
            $computedTotalHt += $lineTotal;

            $designation = trim((string) ($line->getDesignation() ?? ''));
            if ($designation === '') {
                $designation = (string) ($line->getArticle()?->getLibelle() ?? ('Ligne ' . $lineIndex));
            }

            $articleLabel = (string) ($line->getArticle()?->getLibelle() ?? '');
            $refCandidate = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', strtoupper($articleLabel)));
            if ($refCandidate === '') {
                $refCandidate = 'L' . str_pad((string) $lineIndex, 3, '0', STR_PAD_LEFT);
            }
            $reference = substr($refCandidate, 0, 8);

            $lineItems[] = [
                'reference' => $reference,
                'designation' => $designation,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'total' => $lineTotal,
            ];

            $lineIndex++;
        }

        $subjectText = 'Facture ' . $invoiceRef;
        if (isset($lineItems[0]['designation']) && trim((string) $lineItems[0]['designation']) !== '') {
            $subjectText = (string) $lineItems[0]['designation'];
        }

        $invoiceMontant = $invoice->getMontant();
        $totalHt = ($invoiceMontant !== null && (float) $invoiceMontant > 0)
            ? (float) $invoiceMontant
            : $computedTotalHt;
        $totalTva = round($totalHt * ($taxRate / 100), 2);
        $totalTtc = round($totalHt + $totalTva, 2);

        $paymentLabel = $invoice->getReglement()?->getLibelle();
        $paymentDelay = $invoice->getReglement()?->getEcheance();
        $paymentText = $paymentLabel
            ? $paymentLabel . ($paymentDelay ? (' a ' . $paymentDelay . ' jours') : '')
            : '';

        $sellerRc = trim((string) ($dossier?->getRc() ?? ''));
        $sellerIce = $this->resolveSellerIce(
            (string) ($invoice->getSellerVatNumber() ?? ''),
            (string) ($invoice->getSellerSiren() ?? ''),
            (string) ($invoice->getSellerSiret() ?? '')
        );
        if ($sellerIce === '') {
            $sellerIce = trim((string) ($dossier?->getSiret() ?? ''));
        }
        $sellerVatNumber = trim((string) ($invoice->getSellerVatNumber() ?? ''));
        if ($sellerVatNumber === '') {
            $sellerVatNumber = trim((string) ($dossier?->getTvaintra() ?? ''));
        }
        $sellerNaf = trim((string) ($dossier?->getNaf() ?? ''));
        $sellerEmail = trim((string) ($dossier?->getEmail() ?? ''));
        $sellerPhone = trim((string) ($dossier?->getTel() ?? ''));
        $bankIban = trim((string) ($dossier?->getIban() ?? ''));
        $bankBic = trim((string) ($dossier?->getBic() ?? ''));
        if ($sellerIce === '') {
            $sellerIce = '-';
        }
        if ($sellerRc === '') {
            $fallbackRc = (string) ($invoice->getSellerSiren() ?? $invoice->getSellerSiret() ?? '');
            $sellerRc = $fallbackRc !== '' ? $fallbackRc : '-';
        }
        $sellerSc = implode(', ', array_slice($sellerAddressLines, 0, 2));
        if ($sellerSc === '') {
            $sellerSc = trim($sellerCountry);
        }
        if ($sellerSc === '') {
            $sellerSc = '-';
        }
        if ($sellerNaf === '') {
            $sellerNaf = '-';
        }

        return [
            'invoice_ref' => $invoiceRef,
            'invoice_number' => (string) ($invoice->getPieceno() ?? ''),
            'invoice_date' => $invoiceDate,
            'due_date' => $dueDate,
            'currency' => $currency,
            'tax_rate' => $taxRate,
            'total_ht' => $totalHt,
            'total_tva' => $totalTva,
            'total_ttc' => $totalTtc,
            'payment_text' => $paymentText,
            'payment_delay' => (string) ($paymentDelay ?? ''),
            'seller_name' => (string) ($dossier?->getNom() ?? 'Votre Societe'),
            'seller_id' => (string) ($invoice->getSellerSiren() ?? $invoice->getSellerSiret() ?? $dossier?->getRc() ?? ''),
            'seller_rc' => $sellerRc,
            'seller_ice' => $sellerIce,
            'seller_vat_number' => $sellerVatNumber,
            'seller_naf' => $sellerNaf,
            'seller_sc' => $sellerSc,
            'seller_email' => $sellerEmail,
            'seller_phone' => $sellerPhone,
            'seller_address_lines' => $sellerAddressLines,
            'seller_country' => $sellerCountry,
            'seller_logo' => $this->resolveLogoPath((string) ($dossier?->getLogo() ?? '')),
            'buyer_name' => (string) ($client?->getRaisonSociale() ?? $client?->getNom() ?? 'Client'),
            'buyer_code' => $buyerCode,
            'buyer_address_lines' => $buyerAddressLines,
            'buyer_postcode' => $buyerPostcode,
            'buyer_city' => $buyerCity,
            'buyer_country' => $buyerCountry,
            'buyer_email' => (string) ($client?->getEmail() ?? ''),
            'line_items' => $lineItems,
            'subject_text' => $subjectText,
            'bank_iban' => $bankIban,
            'bank_bic' => $bankBic,
        ];
    }

    /**
     * Normalize VAT rate to the closest allowed French rate to satisfy EN16931/Factur-X validations.
     */
    private function normalizeVatRate(float $rate): float
    {
        if ($rate < 0) {
            return 0.0;
        }

        // Allowed FR VAT rates (Flux2 CII/EN16931): 0, 2.1, 5.5, 10, 20
        $allowed = [0.0, 2.1, 5.5, 10.0, 20.0];

        // If already within ±0.01 of an allowed value, snap to it.
        foreach ($allowed as $allowedRate) {
            if (abs($rate - $allowedRate) < 0.01) {
                return $allowedRate;
            }
        }

        // Otherwise pick the closest allowed rate.
        $closest = $allowed[0];
        $minDiff = PHP_FLOAT_MAX;
        foreach ($allowed as $allowedRate) {
            $diff = abs($rate - $allowedRate);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $allowedRate;
            }
        }

        return $closest;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildClassicHtml(array $data, bool $includeFooter = true): string
    {
        $rows = '';
        foreach ($data['line_items'] as $item) {
            $rows .= '<tr>'
                . '<td width="12%">' . $this->e($item['reference']) . '</td>'
                . '<td width="48%">' . $this->e($item['designation']) . '</td>'
                . '<td width="10%" class="r">' . number_format((float) $item['quantity'], 2, ',', ' ') . '</td>'
                . '<td width="15%" class="r">' . number_format((float) $item['unit_price'], 2, ',', ' ') . '</td>'
                . '<td width="15%" class="r">' . number_format((float) $item['total'], 2, ',', ' ') . '</td>'
                . '</tr>';
        }

        $sellerLinesHtml = $this->linesToHtml($data['seller_address_lines']);
        $buyerLinesHtml = $this->linesToHtml($data['buyer_address_lines']);
        $buyerCityLine = trim($data['buyer_postcode'] . ' ' . $data['buyer_city']);
        $sellerLogoHtml = '';
        if (!empty($data['seller_logo'])) {
            $sellerLogoHtml = '<div class="seller-logo-wrap"><img class="seller-logo" src="' . $this->e($data['seller_logo']) . '" alt="logo"></div>';
        }
        
        return '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">

<style>

html, body{
margin:0;
padding:0;
}

body{
font-family: DejaVu Sans, sans-serif;
font-size:11px;
color:#333;
}

.page{
position:relative;
min-height:270mm;
padding:15mm 15mm 45mm 15mm;
box-sizing:border-box;
}

/* HEADER */

.header-table{
width:100%;
border-collapse:collapse;
margin-bottom:25px;
}

.header-table td{
width:50%;
vertical-align:top;
}

.seller-logo-wrap{
margin-bottom:8px;
}

.seller-logo{
max-height:55px;
max-width:180px;
object-fit:contain;
}

.company-name{
font-size:16px;
font-weight:bold;
margin-bottom:6px;
}

.company-details{
font-size:11px;
line-height:1.6;
}

/* TITLE */

.title{
font-size:28px;
font-style:italic;
color:#555;
margin:20px 0 12px 0;
}

/* META TABLE */

.meta-table{
width:100%;
border-collapse:collapse;
margin-bottom:20px;
}

.meta-table th{
background:#e6e6e6;
padding:6px;
font-size:8px;
text-align:left;
}

.meta-table td{
padding:6px;
border-bottom:1px solid #ddd;
}

/* PRODUCT TABLE */

.lines{
width:100%;
border-collapse:collapse;
margin-bottom:20px;
}

.lines thead{
display:table-header-group;
}

.lines th{
background:#dcdcdc;
font-size:11px;
padding:7px 6px;
text-align:left;
}

.lines td{
padding:7px 6px;
border-bottom:1px solid #ddd;
}

.r{
text-align:right;
}

.c{
text-align:center;
}

/* TOTALS */

.totals{
width:260px;
margin-left:auto;
border-collapse:collapse;
margin-bottom:20px;
}

.totals td{
padding:5px 8px;
}

.totals td:last-child{
text-align:right;
font-weight:bold;
}

.totals .ttc td{
font-weight:bold;
font-size:12px;
border-top:2px solid #333;
border-bottom:2px solid #333;
}

/* PAYMENT */

.payment{
font-size:10px;
line-height:1.6;
margin-bottom:25px;
margin-top:30px;
}

.payment strong{
display:block;
margin-top:6px;
}

/* LEGAL */

.legal{
font-size:8px;
color:#666;
margin-top:12px;
}

.footer{
width:100%;
padding:0 10mm;
box-sizing:border-box;
}

.footer-box{
background:#dce9f7;
border-radius:10px;
padding:8px 12px;
}

.footer-table{
width:100%;
border-collapse:collapse;
text-align:center;
font-size:9px;
color:#2f3e4e;
line-height:1.4;
}

.footer-table td{
padding:2px 10px;
vertical-align:middle;
}

.footer-head td{
font-weight:bold;
font-size:10px;
}

</style>

</head>

<body>

<div class="page">

<table class="header-table">
<tr>

<td>
'.$sellerLogoHtml.'
<div class="company-name">'.$this->e($data['seller_name']).'</div>
<div class="company-details">
'.$sellerLinesHtml.'<br>
'.$this->e($data['seller_country']).'
</div>
</td>

<td>
<div class="company-name">'.$this->e($data['buyer_name']).'</div>
<div class="company-details">
'.$buyerLinesHtml.'<br>
'.$this->e($buyerCityLine).'<br>
'.$this->e($data['buyer_country']).'
</div>
</td>

</tr>
</table>

<div class="title">Facture</div>

<table class="meta-table">

<tr>
<th>Date</th>
<th>N° pièce</th>
<th>Client</th>
<th>Référence</th>
</tr>

<tr>
<td>'.$this->e($data['invoice_date']).'</td>
<td>'.$this->e($data['invoice_number']).'</td>
<td>'.$this->e($data['buyer_code']).'</td>
<td>'.$this->e($data['invoice_ref']).'</td>
</tr>

</table>

<table class="lines">

<thead>
<tr>
<th width="12%">Référence</th>
<th width="48%">Désignation</th>
<th width="10%" class="c">Quantité</th>
<th width="15%" class="r">Prix unitaire</th>
<th width="15%" class="r">Montant</th>
</tr>
</thead>

<tbody>
'.$rows.'
</tbody>

</table>

<table class="totals">

<tr>
<td>Total HT</td>
<td>'.number_format((float)$data['total_ht'],2,',',' ').'</td>
</tr>

<tr>
<td>TVA ('.number_format((float)$data['tax_rate'],2,',',' ').'%)</td>
<td>'.number_format((float)$data['total_tva'],2,',',' ').'</td>
</tr>

<tr class="ttc">
<td>Total TTC</td>
<td>'.number_format((float)$data['total_ttc'],2,',',' ').' '.$this->e($data['currency']).'</td>
</tr>

</table>

<div class="payment">

<strong>Mode de règlement :</strong>
'.$this->e($data['payment_text'] !== "" ? $data["payment_text"] : "paiement à réception de facture").' .<br>

<strong>Coordonnées bancaires :</strong> . <br>

IBAN : '.($data["bank_iban"] !== "" ? $this->e($data["bank_iban"]) : "").'<br>
BIC : '.($data["bank_bic"] !== "" ? $this->e($data["bank_bic"]) : "").'

</div>

<div class="legal">
Pénalités de retard applicables conformément à la loi 2008-776 du 4 août 2008.
Indemnité forfaitaire pour frais de recouvrement : 40 €.
</div>

</div>

</body>
</html>';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildModernHtml(array $data, bool $includeFooter = true): string
    {
        $rows = '';
        foreach ($data['line_items'] as $item) {
            $rows .= '<tr>'
                . '<td class="c">' . number_format((float) $item['quantity'], 2, ',', ' ') . '</td>'
                . '<td>' . $this->e($item['designation']) . '</td>'
                . '<td class="r">' . number_format((float) $item['unit_price'], 2, ',', ' ') . '</td>'
                . '<td class="r">' . number_format((float) $item['total'], 2, ',', ' ') . '</td>'
                . '</tr>';
        }

        $sellerLinesHtml = $this->linesToHtml($data['seller_address_lines']);
        $buyerLinesHtml = $this->linesToHtml($data['buyer_address_lines']);
        $buyerCityLine = trim($data['buyer_postcode'] . ' ' . $data['buyer_city']);
        $sellerCityLine = trim($data['seller_country']);
        $sellerLogoHtml = '';
        if (!empty($data['seller_logo'])) {
            $sellerLogoHtml = '<div style="margin-bottom:10px;"><img src="' . $this->e($data['seller_logo']) . '" alt="logo" style="max-height:55px; max-width:180px; object-fit:contain;"></div>';
        }

        // Payment and bank info section (in main content)
        $contentFooterHtml = '';
        if ($includeFooter) {
            $contentFooterHtml = '<div style="width: 100%; font-size: 11px; color: #5a6d81; line-height: 1.5; margin-bottom: 15px; margin-top: 10px; padding: 12px; background: #f9fafb; border: 1px solid #e0e5ed; border-radius: 4px;">
<strong>Conditions de paiement:</strong> ' . $this->e($data['payment_text'] !== '' ? $data['payment_text'] : 'paiement à réception de facture') . '<br>
<strong>Coordonnées bancaires:</strong><br>
IBAN: ' . ($data['bank_iban'] !== '' ? $this->e($data['bank_iban']) : '') . '<br>
Code SWIFT: ' . ($data['bank_bic'] !== '' ? $this->e($data['bank_bic']) : '') . '</div>';
        }

        return '<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
html, body { height: 100%; margin: 0; padding: 0; }
body { font-family: DejaVu Sans, sans-serif; font-size: 15px; color: #203040; }
.container { position: relative; width: 100%; min-height: 297mm; padding-bottom: 28mm; box-sizing: border-box; }
.content { padding: 10px 12px; }
.head { width: 100%; border-collapse: separate; border-spacing: 0; }
.left { font-size: 14.4px; line-height: 1.6; vertical-align: top; padding-right: 22px; padding-top: 10px; }
.left strong { font-size: 15.6px; color: #1f4469; }
.right-wrap { vertical-align: top; padding-left: 180px; padding-top: 0; }
.right-block { display: block; width: 324px; margin-left: auto; text-align: left; }
.banner { text-align: center; background: #b9d7f3; color: #000000; font-size: 40.8px; letter-spacing: 7px; font-weight: 700; padding: 10px 12px; border-radius: 0; margin-bottom: 20px; }
.right { font-size: 14.4px; line-height: 1.55; text-align: left !important; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e5ed; }
.right strong { font-size: 15.6px; color: #1f4469; }
.cards { width: 240px; border-collapse: collapse; margin-bottom: 12px; margin-top: 15px; }
.card { border: 1px solid #d7e3ef; background: #edf5fd; padding: 13px 0; font-size: 14.4px; line-height: 1.5;  }
.label { color: #4a5d72; }
.subject { margin: 8px 0 10px 0; font-size: 14.4px; color: #2f4358; }
.lines { width: 90%; margin: 0 auto 20px auto; border-collapse: collapse; }
.lines th { background: #e7eef6; border-bottom: 1px solid #d4dde7; display: table-header-group; color: #4f6071; font-size: 13.2px; padding: 10px 11px; text-align: left; }
.lines td { border-bottom: 1px solid #e8eef4; padding: 12px; font-size: 14.4px; }
.c { text-align: center; }
.r { text-align: right; }
.totals { width: 45.6%; margin-left: auto; border-collapse: collapse; margin-top: 15px; margin-bottom: 20px; }
.totals td { padding: 9px 8px; border-bottom: 1px dashed #ccd7e2; font-size: 14.4px; }
.totals td:first-child { color: #4f6277; }
.totals td:last-child { text-align: right; font-weight: 700; }
.totals tr.ttc td { border-top: 1px solid #9db4c9; border-bottom: 1px solid #9db4c9; font-size: 16.8px; color: #143f67; }
.footer-wrap { position: absolute; left: 0; right: 0; bottom: 0; padding: 14px 12px; box-sizing: border-box; width: 100%;}
.foot-main { width: 100%; border-collapse: collapse; border-top: 1px solid #d7e3ef; }
.foot-main td { font-size: 11.4px; color: #62798f; padding: 10px 5px; text-align: center; }
.foot-main .line-top td { width: 33.33%; }
.foot-main .line-email td { padding-top: 2px; font-size: 8.4px; }
</style></head>
<body><div class="container">
<div class="content">

<table class="head"><tr>
<td width="44%" class="left" style="vertical-align: top;">' . $sellerLogoHtml . '<strong>' . $this->e($data['seller_name']) . '</strong><br>'
    . $sellerLinesHtml . '<br>'
    . $this->e($sellerCityLine) . '<br><br>'
    . '<div class="card">'
    . '<span class="label">N de facture:</span> <strong>' . $this->e($data['invoice_ref']) . '</strong><br>'
    . '<span class="label">Date:</span> <strong>' . $this->e($data['invoice_date']) . '</strong><br>'
    . '<span class="label">N client:</span> <strong>' . $this->e($data['buyer_code']) . '</strong>'
    . '</div></td>
<td width="56%" class="right-wrap" style="vertical-align: top;"><div class="banner">FACTURE</div><br><br><br><br><br><div class="right" style="margin-top: 0;"><strong>' . $this->e($data['buyer_name']) . '</strong><br>'
    . $buyerLinesHtml . '<br>'
    . $this->e($buyerCityLine) . '<br>'
    . $this->e($data['buyer_country']) . '</div></td>
</tr></table>

<div class="subject">Intitule: ' . $this->e($data['subject_text']) . '</div>


<table class="lines">
<thead><tr><th width="14%" class="c">Quantite</th><th width="52%">Designation</th><th width="17%" class="r">Prix unit HT</th><th width="17%" class="r">Prix total HT</th></tr></thead>
<tbody>' . $rows . '</tbody>
</table>

<div style="height: 20px;"></div>

<table class="totals">
<tr><td>Total Hors Taxe</td><td>' . number_format((float) $data['total_ht'], 2, ',', ' ') . ' ' . $this->e($data['currency']) . '</td></tr>
<tr><td>TVA (' . number_format((float) $data['tax_rate'], 2, ',', ' ') . '%)</td><td>' . number_format((float) $data['total_tva'], 2, ',', ' ') . ' ' . $this->e($data['currency']) . '</td></tr>
<tr class="ttc"><td>Total TTC</td><td>' . number_format((float) $data['total_ttc'], 2, ',', ' ') . ' ' . $this->e($data['currency']) . '</td></tr>
</table>

<div style="height: 20px;"></div>

' . $contentFooterHtml . '

</div>
</div></body></html>';
    }

    /**
     * @param array<int, string> $lines
     */
    private function linesToHtml(array $lines): string
    {
        if ($lines === []) {
            return '-';
        }

        return implode('<br>', array_map(fn (string $line): string => $this->e($line), $lines));
    }

    private function extractCountry(string $address, string $fallback): string
    {
        $parts = preg_split('/[\r\n,]+/', $address) ?: [];
        $parts = $this->compactLines($parts);
        if ($parts === []) {
            return $fallback;
        }

        $last = strtolower((string) end($parts));
        if ($last === 'france' || $last === 'maroc' || $last === 'morocco') {
            return ucfirst($last);
        }

        return $fallback;
    }

    private function resolveSellerIce(string $sellerVat, string $sellerSiren, string $sellerSiret): string
    {
        $candidates = [$sellerVat, $sellerSiren, $sellerSiret];
        foreach ($candidates as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';
            if ($digits !== '') {
                return $digits;
            }
        }

        return '';
    }

    private function resolveLogoPath(string $logo): string
    {
        $logo = trim($logo);
        if ($logo === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $logo)) {
            return $logo;
        }

        $candidatePaths = [];
        if (str_starts_with($logo, '/')) {
            $candidatePaths[] = $this->projectDir . '/public' . $logo;
        }
        $candidatePaths[] = $this->projectDir . '/public/' . ltrim($logo, '/');
        $candidatePaths[] = $logo;

        foreach ($candidatePaths as $path) {
            $resolved = realpath($path);
            if ($resolved !== false && is_file($resolved)) {
                return str_replace('\\', '/', $resolved);
            }
        }

        return '';
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function compactLines(array $lines): array
    {
        $result = [];
        foreach ($lines as $line) {
            $value = trim((string) $line);
            if ($value === '') {
                continue;
            }
            $result[] = $value;
        }

        return array_values(array_unique($result));
    }

    private function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
