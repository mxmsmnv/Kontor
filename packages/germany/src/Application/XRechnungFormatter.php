<?php

declare(strict_types=1);

namespace Kontor\Germany\Application;

use Kontor\Germany\DTO\LocalizedInvoiceInput;
use Kontor\Germany\DTO\LocalizedLineItemInput;
use Kontor\Germany\DTO\LocalizedPartyInput;

/**
 * The "country-specific document formats" milestone. Produces a
 * UBL-inspired XML structure carrying XRechnung's core elements —
 * invoice number, dates, seller/buyer party, line items, tax and
 * monetary totals — using PHP's own `DOMDocument` (no third-party XML/UBL
 * library, the same "avoid a heavy dependency for a narrow need" call
 * `kontor/documents` already made for its own template engine).
 *
 * This is deliberately a simplified, illustrative subset of the real
 * XRechnung/EN16931 semantic data model, not a certified-compliant
 * implementation — full compliance needs the complete CIUS schema and
 * Schematron business-rule validation (hundreds of rules), well beyond
 * this substage's "foundations" scope. See this package's own README
 * "Not in scope" for what's missing. ZUGFeRD (the PDF+embedded-XML
 * sibling format) is declared as a supported format
 * (`GermanyLocalizationProvider::supportedDocumentFormats()`) but not
 * implemented here — it would combine this same XML with
 * `kontor/documents`'s existing PDF renderer, which this package doesn't
 * depend on.
 */
final class XRechnungFormatter
{
    public function format(LocalizedInvoiceInput $invoice): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('Invoice');
        $root->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $root->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $document->appendChild($root);

        $this->appendText($document, $root, 'cbc:ID', $invoice->invoiceNumber);
        $this->appendText($document, $root, 'cbc:IssueDate', $invoice->issueDate->format('Y-m-d'));

        if ($invoice->dueDate !== null) {
            $this->appendText($document, $root, 'cbc:DueDate', $invoice->dueDate->format('Y-m-d'));
        }

        $this->appendText($document, $root, 'cbc:DocumentCurrencyCode', $invoice->currencyCode);

        $root->appendChild($this->partyElement($document, 'cac:AccountingSupplierParty', $invoice->seller));
        $root->appendChild($this->partyElement($document, 'cac:AccountingCustomerParty', $invoice->buyer));

        foreach ($invoice->lineItems as $index => $lineItem) {
            $root->appendChild($this->lineElement($document, $index + 1, $lineItem));
        }

        $root->appendChild($this->taxTotalElement($document, $invoice));
        $root->appendChild($this->monetaryTotalElement($document, $invoice));

        return (string) $document->saveXML();
    }

    private function partyElement(\DOMDocument $document, string $wrapperName, LocalizedPartyInput $party): \DOMElement
    {
        $wrapper = $document->createElement($wrapperName);
        $partyElement = $document->createElement('cac:Party');

        $this->appendText($document, $partyElement, 'cbc:Name', $party->name);

        $address = $document->createElement('cac:PostalAddress');
        $this->appendText($document, $address, 'cbc:StreetName', $party->streetAddress);
        $this->appendText($document, $address, 'cbc:CityName', $party->city);
        $this->appendText($document, $address, 'cbc:PostalZone', $party->postalCode);
        $this->appendText($document, $address, 'cbc:Country', $party->countryCode);
        $partyElement->appendChild($address);

        if ($party->taxId !== null) {
            $taxScheme = $document->createElement('cac:PartyTaxScheme');
            $this->appendText($document, $taxScheme, 'cbc:CompanyID', $party->taxId);
            $partyElement->appendChild($taxScheme);
        }

        $wrapper->appendChild($partyElement);

        return $wrapper;
    }

    private function lineElement(\DOMDocument $document, int $lineNumber, LocalizedLineItemInput $lineItem): \DOMElement
    {
        $line = $document->createElement('cac:InvoiceLine');
        $this->appendText($document, $line, 'cbc:ID', (string) $lineNumber);
        $this->appendText($document, $line, 'cbc:InvoicedQuantity', $lineItem->quantity);
        $this->appendText($document, $line, 'cbc:LineExtensionAmount', $this->formatAmount($lineItem->lineTotal->amountMinor()));

        $item = $document->createElement('cac:Item');
        $this->appendText($document, $item, 'cbc:Description', $lineItem->description);

        $taxCategory = $document->createElement('cac:ClassifiedTaxCategory');
        $this->appendText($document, $taxCategory, 'cbc:Percent', $lineItem->taxRatePercent);
        $item->appendChild($taxCategory);
        $line->appendChild($item);

        $price = $document->createElement('cac:Price');
        $this->appendText($document, $price, 'cbc:PriceAmount', $this->formatAmount($lineItem->unitPrice->amountMinor()));
        $line->appendChild($price);

        return $line;
    }

    private function taxTotalElement(\DOMDocument $document, LocalizedInvoiceInput $invoice): \DOMElement
    {
        $taxTotal = $document->createElement('cac:TaxTotal');
        $this->appendText($document, $taxTotal, 'cbc:TaxAmount', $this->formatAmount($invoice->totalTax->amountMinor()));

        return $taxTotal;
    }

    private function monetaryTotalElement(\DOMDocument $document, LocalizedInvoiceInput $invoice): \DOMElement
    {
        $monetaryTotal = $document->createElement('cac:LegalMonetaryTotal');
        $this->appendText($document, $monetaryTotal, 'cbc:TaxExclusiveAmount', $this->formatAmount($invoice->totalNet->amountMinor()));
        $this->appendText($document, $monetaryTotal, 'cbc:TaxInclusiveAmount', $this->formatAmount($invoice->totalGross->amountMinor()));
        $this->appendText($document, $monetaryTotal, 'cbc:PayableAmount', $this->formatAmount($invoice->totalGross->amountMinor()));

        return $monetaryTotal;
    }

    /**
     * Minor units are always 2-decimal-place in this codebase's own
     * `Money` convention (kontor.md#10.5) — no per-currency decimal-place
     * table exists anywhere yet, so this doesn't invent one.
     */
    private function formatAmount(int $amountMinor): string
    {
        return number_format($amountMinor / 100, 2, '.', '');
    }

    private function appendText(\DOMDocument $document, \DOMElement $parent, string $name, string $value): void
    {
        $parent->appendChild($document->createElement($name, htmlspecialchars($value, ENT_XML1)));
    }
}
