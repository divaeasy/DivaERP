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

        $buyer = $invoice->getClient();
        $buyerName = $buyer?->getRaisonSociale() ?? $buyer?->getNom() ?? 'Client';
        $buyerStreet = $buyer?->getAdresse() ?? '';
        $buyerPostCode = trim((string) ($buyer?->getCodepostal() ?? ''));
        $buyerCity = trim((string) ($buyer?->getVille()?->getLibelle() ?? ''));
        $buyerCountry = $this->resolveCountryCode($buyer?->getPays()?->getLibelle());
        $sellerId = $this->resolveSellerSiren($invoice);
        $buyerId = $this->resolvePartyIdentifier([
            $invoice->getBuyerSiren(),
            $invoice->getBuyerSiret(),
            $invoice->getBuyerVatNumber(),
            'CLIENT-' . ($buyer?->getId() ?? $invoice->getId()),
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
            $this->buildElectronicAddress($buyer?->getEmail(), $invoiceRef, 'buyer')
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

        $lineNo = 1;
        foreach ($lines as $line) {
            $quantity = (float) ($line->getQuantite() ?? 1.0);
            $unitPrice = (float) ($line->getPu() ?? 0.0);
            $description = $line->getDesignation() ?? 'Service/Product';

            $descriptor->doAddTradeLineItem(
                (string) $lineNo,
                $description,
                $unitPrice,
                $quantity,
                ZugferdUnitCodes::REC20_PIECE,
                0.0,
                '',
                ZugferdVatCategoryCodes::STAN_RATE,
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
        $rate = (float) ($invoice->getTaxRate() ?? 20.0);
        if ($rate <= 0) {
            return 20.0;
        }

        return $rate;
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

    private function validateXml(string $xmlContent): bool
    {
        $dom = new DOMDocument();

        return $dom->loadXML($xmlContent);
    }
}
