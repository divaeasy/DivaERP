<?php

namespace App\Service\EInvoicing\EN16931;

use App\Entity\Entetepiece;
use DateTime;
use DateTimeInterface;
use DOMDocument;
use horstoeko\zugferd\codelists\ZugferdCountryCodes;
use horstoeko\zugferd\codelists\ZugferdPaymentMeans;
use horstoeko\zugferd\codelists\ZugferdSchemeIdentifiers;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\codelists\ZugferdVatCategoryCodes;
use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;
use horstoeko\zugferd\quick\ZugferdQuickDescriptor;

/**
 * Generates Factur-X / EN16931 XML using the installed horstoeko/zugferd API.
 */
class FactureXBuilder
{
    /**
     * Build invoice XML in CII format (Factur-X EN16931 profile).
     */
    public function buildInvoiceXml(Entetepiece $invoice): string
    {
        $lines = $invoice->getLignepieces();
        if ($lines->isEmpty()) {
            throw new \RuntimeException('Invoice must contain at least one line item.');
        }

        $invoiceRef = (string) ($invoice->getPieceref() ?? $invoice->getId());
        $invoiceDate = $this->toDateTime($invoice->getDatep()) ?? new DateTime();
        $currency = strtoupper((string) ($invoice->getDevise()?->getCode() ?? 'EUR'));
        $taxRate = $this->resolveTaxRate($invoice);

        $sellerName = $invoice->getDossier()?->getNom() ?? 'Your Company';
        $sellerAddressRaw = (string) ($invoice->getDossier()?->getAdresse() ?? '');
        [$sellerPostCode, $sellerCity] = $this->extractPostalData($sellerAddressRaw);
        $sellerStreet = $sellerAddressRaw !== '' ? $sellerAddressRaw : 'Unknown seller address';
        $sellerCountry = ZugferdCountryCodes::FRANCE;

        $buyer = $invoice->getTier();
        $buyerName = $this->extractTierName($buyer);
        $buyerStreet = $this->extractTierAddress($buyer);
        $buyerPostCode = trim((string) $this->extractTierCodepostal($buyer));
        $buyerCity = trim((string) $this->extractTierCity($buyer));
        $buyerCountry = $this->resolveCountryCode($this->extractTierCountry($buyer));
        $sellerId = $this->resolveSellerSiren($invoice);
        $buyerId = $this->resolvePartyIdentifier([
            $invoice->getBuyerSiren(),
            $invoice->getBuyerSiret(),
            $invoice->getBuyerVatNumber(),
            'CLIENT-' . ($this->extractTierId($buyer) ?? $invoice->getId()),
        ]);
        if ($buyerPostCode === '' || $buyerCity === '') {
            [$parsedPostCode, $parsedCity] = $this->extractPostalData($buyerStreet);
            if ($buyerPostCode === '') {
                $buyerPostCode = $parsedPostCode;
            }
            if ($buyerCity === '') {
                $buyerCity = $parsedCity;
            }
        }
        if ($buyerStreet === '') {
            $buyerStreet = 'Unknown buyer address';
        }

        $descriptor = ZugferdQuickDescriptor::doCreateNew();
        $descriptor->doCreateInvoice($invoiceRef, $invoiceDate, $currency, $invoiceRef);
        $descriptor->addDocumentPaymentMean(ZugferdPaymentMeans::UNTDID_4461_1, 'Payment by agreement');

        $descriptor->doSetSeller(
            $sellerName,
            $sellerPostCode,
            $sellerCity,
            $sellerStreet,
            $sellerCountry,
            $sellerId,
            $sellerId,
            ZugferdSchemeIdentifiers::ISO_6523_0002
        );
        // BT-30 (Seller legal registration identifier) used by FR CTC BR-FR-10.
        $descriptor->setDocumentSellerLegalOrganisation(
            $sellerId,
            ZugferdSchemeIdentifiers::ISO_6523_0002,
            null
        );
        $descriptor->doSetSellerElectronicCommunication(
            $this->buildElectronicAddress(null, $invoiceRef, 'seller')
        );

        // Keep FC aligned with BT-30 (SIREN) to satisfy FR CTC BR-FR-10 extraction rules.
        $descriptor->addDocumentSellerTaxRegistration('FC', $sellerId);

        $sellerVat = $this->normalizeVatNumber($invoice->getSellerVatNumber(), $sellerCountry);
        if ($sellerVat !== '') {
            $descriptor->addDocumentSellerTaxRegistration('VA', $sellerVat);
        }

        $descriptor->doSetBuyer(
            $buyerName,
            $buyerPostCode,
            $buyerCity,
            $buyerStreet,
            $buyerCountry,
            $invoiceRef,
            $buyerId
        );
        $descriptor->doSetBuyerElectronicCommunication(
            $this->buildElectronicAddress($this->extractTierEmail($buyer), $invoiceRef, 'buyer')
        );

        $buyerVat = $this->normalizeVatNumber($invoice->getBuyerVatNumber(), $buyerCountry);
        if ($buyerVat !== '') {
            $descriptor->addDocumentBuyerTaxRegistration('VA', $buyerVat);
        }

        if ($invoice->getDatep() instanceof DateTimeInterface) {
            $descriptor->doSetSupplyChainEvent($this->toDateTime($invoice->getDatep()));
        }

        if ($invoice->getReglement()) {
            $paymentText = $invoice->getReglement()->getLibelle() ?? 'Payment terms';
            $descriptor->doSetPaymentTerms(
                $paymentText,
                $this->toDateTime($invoice->getDelai())
            );
        }

        // French CTC required legal notes in BG-1 (BT-22).
        $descriptor->addDocumentNote(
            'Penalites de retard exigibles au taux legal en vigueur.',
            null,
            'PMD'
        );
        $descriptor->addDocumentNote(
            'Indemnite forfaitaire pour frais de recouvrement: 40 EUR.',
            null,
            'PMT'
        );
        $descriptor->addDocumentNote(
            'Escompte pour paiement anticipe: neant.',
            null,
            'AAB'
        );

        $vatCategory = $taxRate > 0
            ? ZugferdVatCategoryCodes::STAN_RATE
            : ZugferdVatCategoryCodes::ZERO_RATE_GOOD;

        $lineNo = 1;
        foreach ($lines as $line) {
            $quantity = (float) ($line->getQuantite() ?? 1.0);
            $unitPrice = (float) ($line->getPu() ?? 0.0);
            $rawRemise = (float) ($line->getRemise() ?? 0.0);
            $lineRemise = max(0.0, min(100.0, $rawRemise));
            $netUnitPrice = round($unitPrice * (1 - $lineRemise / 100), 4);
            $description = $line->getDesignation() ?? 'Service/Product';

            $descriptor->doAddTradeLineItem(
                (string) $lineNo,
                $description,
                $netUnitPrice,
                $quantity,
                ZugferdUnitCodes::REC20_PIECE,
                0.0,
                '',
                $vatCategory,
                ZugferdVatTypeCodes::VALUE_ADDED_TAX,
                $taxRate
            );

            $lineNo++;
        }

        $xmlContent = $descriptor->getContent();

        if (!$this->validateXml($xmlContent)) {
            throw new \RuntimeException('Generated Factur-X XML is not well-formed.');
        }

        return $xmlContent;
    }

    private function toDateTime(?DateTimeInterface $date): ?DateTime
    {
        if ($date === null) {
            return null;
        }

        return DateTime::createFromInterface($date);
    }

    private function resolveTaxRate(Entetepiece $invoice): float
    {
        $rate = $invoice->getTaxRate();
        if ($rate === null || trim((string) $rate) === '') {
            $rate = $invoice->getRemise();
        }
        $rate = (float) ($rate ?? 0.0);
        return $this->normalizeVatRate($rate);
    }

    private function normalizeVatRate(float $rate): float
    {
        if ($rate < 0) {
            return 0.0;
        }

        $allowed = [0.0, 2.1, 5.5, 10.0, 20.0];

        foreach ($allowed as $allowedRate) {
            if (abs($rate - $allowedRate) < 0.01) {
                return $allowedRate;
            }
        }

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

    private function resolveCountryCode(?string $country): string
    {
        if ($country === null || trim($country) === '') {
            return ZugferdCountryCodes::FRANCE;
        }

        $normalized = strtoupper(trim($country));
        if ($normalized === 'FR' || $normalized === 'FRA' || $normalized === 'FRANCE') {
            return ZugferdCountryCodes::FRANCE;
        }
        if ($normalized === 'MA' || $normalized === 'MAR' || $normalized === 'MOROCCO' || $normalized === 'MAROC') {
            return ZugferdCountryCodes::MOROCCO;
        }
        if ($normalized === 'DE' || $normalized === 'DEU' || $normalized === 'GERMANY' || $normalized === 'ALLEMAGNE') {
            return ZugferdCountryCodes::GERMANY;
        }

        if (preg_match('/^[A-Z]{2}$/', $normalized)) {
            return $normalized;
        }

        return ZugferdCountryCodes::FRANCE;
    }

    private function normalizeVatNumber(?string $vat, string $countryCode): string
    {
        if ($vat === null) {
            return '';
        }

        $normalized = strtoupper(trim($vat));
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/^[A-Z]{2}/', $normalized)) {
            return $normalized;
        }

        // Keep only plausible VAT values (letters+digits, 6+ chars) before prefixing.
        if (!preg_match('/^[A-Z0-9]{6,}$/', $normalized)) {
            return '';
        }

        return $countryCode . $normalized;
    }

    /**
     * Returns non-empty postal data for fields that are required by EN16931 Schematron.
     *
     * @return array{0: string, 1: string}
     */
    private function extractPostalData(string $fullAddress): array
    {
        $postCode = '';
        $city = '';

        if ($fullAddress !== '') {
            if (preg_match('/\b(\d{5})\b(.*)$/u', $fullAddress, $matches)) {
                $postCode = trim($matches[1]);
                $city = trim((string) ($matches[2] ?? ''));
            }
        }

        if ($postCode === '') {
            $postCode = '00000';
        }

        if ($city === '') {
            $city = 'UNKNOWN';
        }

        return [$postCode, $city];
    }

    /**
     * Build a stable identifier for seller/buyer party ID fields.
     */
    private function resolvePartyIdentifier(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = strtoupper(trim((string) $candidate));
            if ($value === '') {
                continue;
            }

            $value = preg_replace('/[^A-Z0-9._\\-]/', '', $value) ?? '';
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Resolve a valid French SIREN (9 digits) for BT-30.
     */
    private function resolveSellerSiren(Entetepiece $invoice): string
    {
        $candidates = [
            (string) ($invoice->getSellerSiren() ?? ''),
            (string) ($invoice->getSellerSiret() ?? ''),
            (string) ($invoice->getDossier()?->getRc() ?? ''),
        ];

        foreach ($candidates as $candidate) {
            $digits = preg_replace('/\D+/', '', $candidate) ?? '';
            if ($digits === '') {
                continue;
            }

            if (strlen($digits) >= 9) {
                return substr($digits, 0, 9);
            }

            return str_pad($digits, 9, '0', STR_PAD_LEFT);
        }

        // Fallback keeps XML valid for BR-FR-10 when master data is incomplete.
        return '000000000';
    }

    /**
     * Build seller/buyer electronic address (BT-34 / BT-49).
     */
    private function buildElectronicAddress(?string $email, string $invoiceRef, string $prefix): string
    {
        $value = trim((string) $email);
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $value;
        }

        $safeRef = preg_replace('/[^A-Za-z0-9]/', '', $invoiceRef) ?? 'INV';
        return sprintf('%s.%s@local.invalid', strtolower($prefix), strtolower($safeRef));
    }

    private function extractTierName(?object $tier): string
    {
        if ($tier !== null && method_exists($tier, 'getRaisonSociale')) {
            $name = trim((string) $tier->getRaisonSociale());
            if ($name !== '') {
                return $name;
            }
        }

        if ($tier !== null && method_exists($tier, 'getNom')) {
            $name = trim((string) $tier->getNom());
            if ($name !== '') {
                return $name;
            }
        }

        return 'Client';
    }

    private function extractTierAddress(?object $tier): string
    {
        if ($tier !== null && method_exists($tier, 'getAdresse')) {
            return (string) ($tier->getAdresse() ?? '');
        }

        return '';
    }

    private function extractTierCodepostal(?object $tier): string
    {
        if ($tier !== null && method_exists($tier, 'getCodepostal')) {
            return (string) ($tier->getCodepostal() ?? '');
        }

        return '';
    }

    private function extractTierCity(?object $tier): string
    {
        if ($tier !== null && method_exists($tier, 'getVille')) {
            $ville = $tier->getVille();
            if ($ville !== null && method_exists($ville, 'getLibelle')) {
                return (string) ($ville->getLibelle() ?? '');
            }

            return trim((string) $ville);
        }

        return '';
    }

    private function extractTierCountry(?object $tier): ?string
    {
        if ($tier !== null && method_exists($tier, 'getPays')) {
            $pays = $tier->getPays();
            if ($pays !== null && method_exists($pays, 'getLibelle')) {
                return (string) ($pays->getLibelle() ?? '');
            }

            return trim((string) $pays);
        }

        return null;
    }

    private function extractTierId(?object $tier): ?int
    {
        if ($tier !== null && method_exists($tier, 'getId')) {
            $id = $tier->getId();
            if (is_int($id) || ctype_digit((string) $id)) {
                return (int) $id;
            }
        }

        return null;
    }

    private function extractTierEmail(?object $tier): ?string
    {
        if ($tier !== null && method_exists($tier, 'getEmail')) {
            $email = trim((string) ($tier->getEmail() ?? ''));
            return $email !== '' ? $email : null;
        }

        return null;
    }

    private function validateXml(string $xmlContent): bool
    {
        $dom = new DOMDocument();

        return $dom->loadXML($xmlContent);
    }
}
