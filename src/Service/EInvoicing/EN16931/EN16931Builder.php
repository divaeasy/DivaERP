<?php

namespace App\Service\EInvoicing\EN16931;

use App\Entity\Entetepiece;
use DateTime;
use DOMDocument;
use DOMElement;

/**
 * Generates EN16931 CII (Cross Industry Invoice) XML format
 * Compliant with French Facture-X format
 */
class EN16931Builder
{
    /**
     * Build XML invoice in CII format
     */
    public function buildInvoiceXml(Entetepiece $invoice): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        // Root element with all required namespaces
        $root = $doc->createElementNS('urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100', 'rsm:CrossIndustryInvoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', 'urn:un:unece:uncefact:data:element:unqualified');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', 'urn:un:unece:uncefact:data:type:unqualified');
        $doc->appendChild($root);

        // Header
        $this->addHeader($doc, $root, $invoice);

        // Invoice data
        $this->addSupplyChainTradeTransaction($doc, $root, $invoice);

        return $doc->saveXML();
    }

    private function addHeader(DOMDocument $doc, DOMElement $root, Entetepiece $invoice): void
    {
        $header = $doc->createElement('rsm:SpecifiedExchangedDocumentContext');
        $root->appendChild($header);

        // Document context
        $testIndicator = $doc->createElement('ram:TestIndicator');
        $testIndicator->setAttribute('format', 'boolean');
        $testIndicator->nodeValue = 'false';
        $header->appendChild($testIndicator);

        // Business process type
        $bpType = $doc->createElement('ram:BusinessProcessSpecifiedDocumentContextParameter');
        $idElem = $doc->createElement('ram:ID');
        $idElem->nodeValue = 'urn:fdc:peppol.eu:2017:business:processes:factoring:01:1.0';
        $bpType->appendChild($idElem);
        $header->appendChild($bpType);

        // Guideline
        $guideline = $doc->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guidelineId = $doc->createElement('ram:ID');
        $guidelineId->nodeValue = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:business:processes:factoring:01:1.0';
        $guideline->appendChild($guidelineId);
        $header->appendChild($guideline);
    }

    private function addSupplyChainTradeTransaction(DOMDocument $doc, DOMElement $root, Entetepiece $invoice): void
    {
        $transaction = $doc->createElement('rsm:SupplyChainTradeTransaction');
        $root->appendChild($transaction);

        // Trade Line Items (invoice lines)
        $this->addTradeLineItems($doc, $transaction, $invoice);

        // Trade Agreement
        $this->addTradeAgreement($doc, $transaction, $invoice);

        // Trade Delivery
        $this->addTradeDelivery($doc, $transaction, $invoice);

        // Trade Settlement
        $this->addTradeSettlement($doc, $transaction, $invoice);
    }

    private function addTradeLineItems(DOMDocument $doc, DOMElement $transaction, Entetepiece $invoice): void
    {
        // Get invoice lines from the invoice
        $lines = $invoice->getLignepieces();

        foreach ($lines as $line) {
            $lineItem = $doc->createElement('ram:SupplyChainTradeLineItem');
            $transaction->appendChild($lineItem);

            // Line ID
            $docRef = $doc->createElement('ram:AssociatedDocumentLineDocument');
            $lineId = $doc->createElement('ram:LineID');
            $lineId->nodeValue = (string) $line->getId();
            $docRef->appendChild($lineId);
            $lineItem->appendChild($docRef);

            // Trade Agreement
            $agreement = $doc->createElement('ram:SpecifiedSupplyChainTradeAgreement');
            $lineItem->appendChild($agreement);

            // Seller contact (from seller info)
            $sellerContact = $doc->createElement('ram:SellerTradeParty');
            $agreement->appendChild($sellerContact);

            // Product details
            $product = $doc->createElement('ram:SpecifiedTradeProduct');
            $productName = $doc->createElement('ram:Name');
            $productName->nodeValue = $line->getDesignation() ?? 'Product';
            $product->appendChild($productName);
            $agreement->appendChild($product);

            // Trade Delivery
            $delivery = $doc->createElement('ram:SpecifiedSupplyChainTradeDelivery');
            $billedQuantity = $doc->createElement('ram:BilledQuantity');
            $billedQuantity->setAttribute('unitCode', 'C62'); // Unit code (piece)
            $billedQuantity->nodeValue = (string) $line->getQuantite();
            $delivery->appendChild($billedQuantity);
            $lineItem->appendChild($delivery);

            // Trade Settlement
            $settlement = $doc->createElement('ram:SpecifiedSupplyChainTradeSettlement');
            $lineItem->appendChild($settlement);

            // Monetary Total
            $lineTotal = $doc->createElement('ram:SpecifiedTradeMonetarySummation');
            $lineTotalAmount = $doc->createElement('ram:DuePayableAmount');
            $lineTotalAmount->setAttribute('currencyID', $invoice->getDevise()?->getCode() ?? 'EUR');
            $lineTotalAmount->nodeValue = (string) ($line->getQuantite() * $line->getPu());
            $lineTotal->appendChild($lineTotalAmount);
            $settlement->appendChild($lineTotal);
        }
    }

    private function addTradeAgreement(DOMDocument $doc, DOMElement $transaction, Entetepiece $invoice): void
    {
        $agreement = $doc->createElement('ram:ApplicableSupplyChainTradeAgreement');
        $transaction->appendChild($agreement);

        // Buyer
        $buyer = $doc->createElement('ram:BuyerTradeParty');
        $agreement->appendChild($buyer);

        if ($invoice->getClient()) {
            $buyerName = $doc->createElement('ram:Name');
            $buyerName->nodeValue = $invoice->getClient()->getRaisonSociale() ?? $invoice->getClient()->getNom();
            $buyer->appendChild($buyerName);
        }

        // Seller (placeholder - should come from company settings)
        $seller = $doc->createElement('ram:SellerTradeParty');
        $agreement->appendChild($seller);

        $sellerName = $doc->createElement('ram:Name');
        $sellerName->nodeValue = 'Your Company Name'; // TODO: Get from company settings
        $seller->appendChild($sellerName);
    }

    private function addTradeDelivery(DOMDocument $doc, DOMElement $transaction, Entetepiece $invoice): void
    {
        $delivery = $doc->createElement('ram:ApplicableSupplyChainTradeDelivery');
        $transaction->appendChild($delivery);

        if ($invoice->getDatep()) {
            $eventDateTime = $doc->createElement('ram:ActualDeliverySupplyChainEvent');
            $deliveryDate = $doc->createElement('ram:OccurrenceDateTime');
            $dateTimeString = $doc->createElement('udt:DateTimeString');
            $dateTimeString->setAttribute('format', '102');
            $dateTimeString->nodeValue = $invoice->getDatep()->format('Ymd');
            $deliveryDate->appendChild($dateTimeString);
            $eventDateTime->appendChild($deliveryDate);
            $delivery->appendChild($eventDateTime);
        }
    }

    private function addTradeSettlement(DOMDocument $doc, DOMElement $transaction, Entetepiece $invoice): void
    {
        $settlement = $doc->createElement('ram:ApplicableSupplyChainTradeSettlement');
        $transaction->appendChild($settlement);

        // Payment terms
        if ($invoice->getReglement()) {
            $paymentTerms = $doc->createElement('ram:SpecifiedTradePaymentTerms');
            $description = $doc->createElement('ram:Description');
            $description->nodeValue = $invoice->getReglement()->getLibelle();
            $paymentTerms->appendChild($description);

            // Due date
            if ($invoice->getDelai()) {
                $dueDate = $doc->createElement('ram:DueDateDateTime');
                $dateTimeString = $doc->createElement('udt:DateTimeString');
                $dateTimeString->setAttribute('format', '102');
                $dateTimeString->nodeValue = $invoice->getDelai()->format('Ymd');
                $dueDate->appendChild($dateTimeString);
                $paymentTerms->appendChild($dueDate);
            }

            $settlement->appendChild($paymentTerms);
        }

        // Currency
        $currency = $doc->createElement('ram:InvoiceCurrencyCode');
        $currency->nodeValue = $invoice->getDevise()?->getCode() ?? 'EUR';
        $settlement->appendChild($currency);

        // Monetary summary
        $summary = $doc->createElement('ram:SpecifiedTradeMonetarySummation');
        $settlement->appendChild($summary);

        // Line total
        if ($invoice->getMontant()) {
            $lineTotal = $doc->createElement('ram:LineTotalAmount');
            $lineTotal->setAttribute('currencyID', $invoice->getDevise()?->getCode() ?? 'EUR');
            $lineTotal->nodeValue = (string) $invoice->getMontant();
            $summary->appendChild($lineTotal);
        }

        // Grand total
        if ($invoice->getMontant()) {
            $grandTotal = $doc->createElement('ram:TotalAmount');
            $grandTotal->setAttribute('currencyID', $invoice->getDevise()?->getCode() ?? 'EUR');
            $grandTotal->nodeValue = (string) $invoice->getMontant();
            $summary->appendChild($grandTotal);

            // Due amount
            $dueAmount = $doc->createElement('ram:DuePayableAmount');
            $dueAmount->setAttribute('currencyID', $invoice->getDevise()?->getCode() ?? 'EUR');
            $dueAmount->nodeValue = (string) $invoice->getMontant();
            $summary->appendChild($dueAmount);
        }
    }
}
