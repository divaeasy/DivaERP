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
        $doc->preserveWhiteSpace = false;

        // Root element with all required namespaces for Factur-X
        $root = $doc->createElementNS('urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100', 'rsm:CrossIndustryInvoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:rsm', 'urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ram', 'urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:udt', 'urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100');
        $doc->appendChild($root);

        // Context
        $this->addContext($doc, $root);

        // Exchanged Document
        $this->addExchangedDocument($doc, $root, $invoice);

        // Supply Chain Trade Transaction
        $this->addSupplyChainTradeTransaction($doc, $root, $invoice);

        return $doc->saveXML();
    }

    private function addContext(DOMDocument $doc, DOMElement $root): void
    {
        $context = $doc->createElement('rsm:ExchangedDocumentContext');
        $root->appendChild($context);

        // Business Process Parameter
        $businessProcess = $doc->createElement('ram:BusinessProcessSpecifiedDocumentContextParameter');
        $bpId = $doc->createElement('ram:ID');
        $bpId->nodeValue = 'urn:fdc:peppol.eu:2017:business:processes:factoring:01:1.0';
        $businessProcess->appendChild($bpId);
        $context->appendChild($businessProcess);

        // Guideline Parameter
        $guideline = $doc->createElement('ram:GuidelineSpecifiedDocumentContextParameter');
        $guidelineId = $doc->createElement('ram:ID');
        $guidelineId->nodeValue = 'urn:factur-x.eu:1p0:basic';
        $guideline->appendChild($guidelineId);
        $context->appendChild($guideline);
    }

    private function addExchangedDocument(DOMDocument $doc, DOMElement $root, Entetepiece $invoice): void
    {
        $document = $doc->createElement('rsm:ExchangedDocument');
        $root->appendChild($document);

        // Invoice ID
        $id = $doc->createElement('ram:ID');
        $id->nodeValue = (string) ($invoice->getPieceref() ?? $invoice->getId());
        $document->appendChild($id);

        // Document Type Code (380 = Invoice)
        $typeCode = $doc->createElement('ram:TypeCode');
        $typeCode->nodeValue = '380';
        $document->appendChild($typeCode);

        // Issue Date
        $issueDate = $doc->createElement('ram:IssueDateTime');
        $dateTime = $doc->createElement('udt:DateTimeString');
        $dateTime->setAttribute('format', '102');
        $dateTime->nodeValue = $invoice->getDatep() ? $invoice->getDatep()->format('Ymd') : date('Ymd');
        $issueDate->appendChild($dateTime);
        $document->appendChild($issueDate);

        // Invoice Name
        $name = $doc->createElement('ram:Name');
        $name->nodeValue = 'Facture';
        $document->appendChild($name);
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

        $lineNumber = 1;
        foreach ($lines as $line) {
            $lineItem = $doc->createElement('ram:IncludedSupplyChainTradeLineItem');
            $transaction->appendChild($lineItem);

            // Line Document Reference
            $docRef = $doc->createElement('ram:AssociatedDocumentLineDocument');
            $lineId = $doc->createElement('ram:LineID');
            $lineId->nodeValue = (string) $lineNumber++;
            $docRef->appendChild($lineId);
            $lineItem->appendChild($docRef);

            // Trade Agreement (product details)
            $agreement = $doc->createElement('ram:SpecifiedLineTradeAgreement');
            $lineItem->appendChild($agreement);

            // Gross Price
            $grossPrice = $doc->createElement('ram:GrossPriceProductTradePrice');
            $priceAmount = $doc->createElement('ram:ChargeAmount');
            $priceAmount->nodeValue = number_format($line->getPu() ?? 0, 2, '.', '');
            $grossPrice->appendChild($priceAmount);
            $agreement->appendChild($grossPrice);

            // Net Price
            $netPrice = $doc->createElement('ram:NetPriceProductTradePrice');
            $netPriceAmount = $doc->createElement('ram:ChargeAmount');
            $netPriceAmount->nodeValue = number_format($line->getPu() ?? 0, 2, '.', '');
            $netPrice->appendChild($netPriceAmount);
            $agreement->appendChild($netPrice);

            // Product
            $product = $doc->createElement('ram:SpecifiedTradeProduct');
            $agreement->appendChild($product);

            $productName = $doc->createElement('ram:Name');
            $productName->nodeValue = $line->getDesignation() ?? 'Product';
            $product->appendChild($productName);

            // Delivery
            $delivery = $doc->createElement('ram:SpecifiedLineTradeDelivery');
            $lineItem->appendChild($delivery);

            $billedQuantity = $doc->createElement('ram:BilledQuantity');
            $billedQuantity->setAttribute('unitCode', 'C62');
            $billedQuantity->nodeValue = number_format($line->getQuantite() ?? 0, 2, '.', '');
            $delivery->appendChild($billedQuantity);

            // Settlement
            $settlement = $doc->createElement('ram:SpecifiedLineTradeSettlement');
            $lineItem->appendChild($settlement);

            // Tax
            $tax = $doc->createElement('ram:ApplicableTradeTax');
            $settlement->appendChild($tax);

            $taxType = $doc->createElement('ram:TypeCode');
            $taxType->nodeValue = 'VAT';
            $tax->appendChild($taxType);

            $taxCategory = $doc->createElement('ram:CategoryCode');
            $taxCategory->nodeValue = 'S';
            $tax->appendChild($taxCategory);

            $taxRate = $doc->createElement('ram:RateApplicablePercent');
            $taxRate->nodeValue = '20';
            $tax->appendChild($taxRate);

            // Monetary Summation
            $lineTotal = ($line->getQuantite() ?? 0) * ($line->getPu() ?? 0);
            $monetarySummation = $doc->createElement('ram:SpecifiedTradeSettlementLineMonetarySummation');
            $settlement->appendChild($monetarySummation);

            $lineTotalAmount = $doc->createElement('ram:LineTotalAmount');
            $lineTotalAmount->nodeValue = number_format($lineTotal, 2, '.', '');
            $monetarySummation->appendChild($lineTotalAmount);
        }
    }

    private function addTradeAgreement(DOMDocument $doc, DOMElement $transaction, Entetepiece $invoice): void
    {
        $agreement = $doc->createElement('ram:ApplicableHeaderTradeAgreement');
        $transaction->appendChild($agreement);

        // Seller
        $seller = $doc->createElement('ram:SellerTradeParty');
        $agreement->appendChild($seller);

        // Seller Name
        $sellerName = $doc->createElement('ram:Name');
        $sellerName->nodeValue = $invoice->getDossier()?->getNom() ?? 'Your Company Name';
        $seller->appendChild($sellerName);

        // Seller Postal Address
        if ($invoice->getDossier()?->getAdresse()) {
            $sellerAddress = $doc->createElement('ram:PostalTradeAddress');
            
            $country = $doc->createElement('ram:CountryID');
            $country->nodeValue = 'FR'; // Default to France
            $sellerAddress->appendChild($country);
            
            $line = $doc->createElement('ram:LineOne');
            $line->nodeValue = $invoice->getDossier()->getAdresse();
            $sellerAddress->appendChild($line);
            
            $seller->appendChild($sellerAddress);
        }

        // Seller Tax Registration
        if ($invoice->getDossier()?->getRc()) {
            $taxRegistration = $doc->createElement('ram:SpecifiedTaxRegistration');
            $taxId = $doc->createElement('ram:ID');
            $taxId->setAttribute('schemeID', 'VA'); // VAT scheme
            $taxId->nodeValue = $invoice->getSellerVatNumber() ?? 'UNKNOWN';
            $taxRegistration->appendChild($taxId);
            $seller->appendChild($taxRegistration);
        }

        // Buyer
        $buyer = $doc->createElement('ram:BuyerTradeParty');
        $agreement->appendChild($buyer);

        if ($invoice->getClient()) {
            // Buyer Name
            $buyerName = $doc->createElement('ram:Name');
            $buyerName->nodeValue = $invoice->getClient()->getRaisonSociale() ?? $invoice->getClient()->getNom();
            $buyer->appendChild($buyerName);

            // Buyer Postal Address
            if ($invoice->getClient()->getAdresse()) {
                $buyerAddress = $doc->createElement('ram:PostalTradeAddress');
                
                $country = $doc->createElement('ram:CountryID');
                $country->nodeValue = $invoice->getClient()->getPays()?->getCode() ?? 'FR';
                $buyerAddress->appendChild($country);
                
                $line = $doc->createElement('ram:LineOne');
                $line->nodeValue = $invoice->getClient()->getAdresse();
                $buyerAddress->appendChild($line);
                
                $city = $doc->createElement('ram:CityName');
                $city->nodeValue = $invoice->getClient()->getVille()?->getNom() ?? '';
                $buyerAddress->appendChild($city);
                
                $buyer->appendChild($buyerAddress);
            }

            // Buyer Tax Registration
            if ($invoice->getBuyerVatNumber()) {
                $taxRegistration = $doc->createElement('ram:SpecifiedTaxRegistration');
                $taxId = $doc->createElement('ram:ID');
                $taxId->setAttribute('schemeID', 'VA');
                $taxId->nodeValue = $invoice->getBuyerVatNumber();
                $taxRegistration->appendChild($taxId);
                $buyer->appendChild($taxRegistration);
            }
        }
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
        $settlement = $doc->createElement('ram:ApplicableHeaderTradeSettlement');
        $transaction->appendChild($settlement);

        // Currency Code
        $currency = $doc->createElement('ram:InvoiceCurrencyCode');
        $currency->nodeValue = $invoice->getDevise()?->getCode() ?? 'EUR';
        $settlement->appendChild($currency);

        // Tax calculations
        $taxRate = 20; // Default VAT rate
        $taxableAmount = $invoice->getMontant() ?? 0;
        $taxAmount = $taxableAmount * ($taxRate / 100);

        // Trade Tax (VAT)
        $tradeTax = $doc->createElement('ram:ApplicableTradeTax');
        $settlement->appendChild($tradeTax);

        $taxType = $doc->createElement('ram:TypeCode');
        $taxType->nodeValue = 'VAT';
        $tradeTax->appendChild($taxType);

        $taxCategory = $doc->createElement('ram:CategoryCode');
        $taxCategory->nodeValue = 'S';
        $tradeTax->appendChild($taxCategory);

        $taxRate_elem = $doc->createElement('ram:RateApplicablePercent');
        $taxRate_elem->nodeValue = (string) $taxRate;
        $tradeTax->appendChild($taxRate_elem);

        $calculatedTax = $doc->createElement('ram:CalculatedAmount');
        $calculatedTax->nodeValue = number_format($taxAmount, 2, '.', '');
        $tradeTax->appendChild($calculatedTax);

        $basisAmount = $doc->createElement('ram:BasisAmount');
        $basisAmount->nodeValue = number_format($taxableAmount, 2, '.', '');
        $tradeTax->appendChild($basisAmount);

        // Payment Terms
        if ($invoice->getReglement()) {
            $paymentTerms = $doc->createElement('ram:SpecifiedTradePaymentTerms');
            $settlement->appendChild($paymentTerms);

            $desc = $doc->createElement('ram:Description');
            $desc->nodeValue = $invoice->getReglement()->getLibelle() . ' (' . $invoice->getReglement()->getEcheance() . ' days)';
            $paymentTerms->appendChild($desc);

            // Due date
            if ($invoice->getDelai()) {
                $dueDate = $doc->createElement('ram:DueDateDateTime');
                $dateTime = $doc->createElement('udt:DateTimeString');
                $dateTime->setAttribute('format', '102');
                $dateTime->nodeValue = $invoice->getDelai()->format('Ymd');
                $dueDate->appendChild($dateTime);
                $paymentTerms->appendChild($dueDate);
            }
        }

        // Monetary Summation
        $summary = $doc->createElement('ram:SpecifiedTradeSettlementHeaderMonetarySummation');
        $settlement->appendChild($summary);

        // Line Total Amount
        $lineTotal = $doc->createElement('ram:LineTotalAmount');
        $lineTotal->nodeValue = number_format($taxableAmount, 2, '.', '');
        $summary->appendChild($lineTotal);

        // Tax Total Amount
        $taxTotal = $doc->createElement('ram:TaxTotalAmount');
        $taxTotal->nodeValue = number_format($taxAmount, 2, '.', '');
        $summary->appendChild($taxTotal);

        // Grand Total Amount (TTC)
        $grandTotal = $doc->createElement('ram:GrandTotalAmount');
        $grandTotal->nodeValue = number_format($taxableAmount + $taxAmount, 2, '.', '');
        $summary->appendChild($grandTotal);

        // Due Payable Amount
        $dueAmount = $doc->createElement('ram:DuePayableAmount');
        $dueAmount->nodeValue = number_format($taxableAmount + $taxAmount, 2, '.', '');
        $summary->appendChild($dueAmount);
    }
}
