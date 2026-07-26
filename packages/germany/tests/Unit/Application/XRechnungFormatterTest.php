<?php

declare(strict_types=1);

namespace Kontor\Germany\Tests\Unit\Application;

use Kontor\Germany\Application\XRechnungFormatter;
use Kontor\Germany\DTO\LocalizedInvoiceInput;
use Kontor\Germany\DTO\LocalizedLineItemInput;
use Kontor\Germany\DTO\LocalizedPartyInput;
use Kontor\SDK\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

final class XRechnungFormatterTest extends TestCase
{
    private function invoice(): LocalizedInvoiceInput
    {
        return new LocalizedInvoiceInput(
            invoiceNumber: 'INV-2026-001',
            issueDate: new \DateTimeImmutable('2026-01-15'),
            dueDate: new \DateTimeImmutable('2026-02-15'),
            currencyCode: 'EUR',
            seller: new LocalizedPartyInput('Acme GmbH', 'Hauptstraße 1', 'Berlin', '10115', 'DE', 'DE811569869'),
            buyer: new LocalizedPartyInput('Kunde AG', 'Nebenstraße 2', 'Munich', '80331', 'DE'),
            lineItems: [
                new LocalizedLineItemInput('Consulting services', '10', Money::ofMinor(10000, 'EUR'), Money::ofMinor(100000, 'EUR'), '19'),
            ],
            totalNet: Money::ofMinor(100000, 'EUR'),
            totalTax: Money::ofMinor(19000, 'EUR'),
            totalGross: Money::ofMinor(119000, 'EUR'),
        );
    }

    private function xpath(string $xml): \DOMXPath
    {
        $document = new \DOMDocument();
        $document->loadXML($xml);

        $xpath = new \DOMXPath($document);
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

        return $xpath;
    }

    public function test_produces_well_formed_xml(): void
    {
        $xml = (new XRechnungFormatter())->format($this->invoice());

        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML($xml));
    }

    public function test_header_fields(): void
    {
        $xpath = $this->xpath((new XRechnungFormatter())->format($this->invoice()));

        $this->assertSame('INV-2026-001', $xpath->query('//cbc:ID')->item(0)->textContent);
        $this->assertSame('2026-01-15', $xpath->query('//cbc:IssueDate')->item(0)->textContent);
        $this->assertSame('2026-02-15', $xpath->query('//cbc:DueDate')->item(0)->textContent);
        $this->assertSame('EUR', $xpath->query('//cbc:DocumentCurrencyCode')->item(0)->textContent);
    }

    public function test_seller_and_buyer_parties(): void
    {
        $xpath = $this->xpath((new XRechnungFormatter())->format($this->invoice()));

        $this->assertSame('Acme GmbH', $xpath->query('//cac:AccountingSupplierParty//cbc:Name')->item(0)->textContent);
        $this->assertSame('DE811569869', $xpath->query('//cac:AccountingSupplierParty//cbc:CompanyID')->item(0)->textContent);
        $this->assertSame('Kunde AG', $xpath->query('//cac:AccountingCustomerParty//cbc:Name')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//cac:AccountingCustomerParty//cbc:CompanyID')->length);
    }

    public function test_line_items(): void
    {
        $xpath = $this->xpath((new XRechnungFormatter())->format($this->invoice()));

        $this->assertSame('Consulting services', $xpath->query('//cac:InvoiceLine//cbc:Description')->item(0)->textContent);
        $this->assertSame('10', $xpath->query('//cac:InvoiceLine/cbc:InvoicedQuantity')->item(0)->textContent);
        $this->assertSame('1000.00', $xpath->query('//cac:InvoiceLine/cbc:LineExtensionAmount')->item(0)->textContent);
        $this->assertSame('100.00', $xpath->query('//cac:Price/cbc:PriceAmount')->item(0)->textContent);
        $this->assertSame('19', $xpath->query('//cac:ClassifiedTaxCategory/cbc:Percent')->item(0)->textContent);
    }

    public function test_totals(): void
    {
        $xpath = $this->xpath((new XRechnungFormatter())->format($this->invoice()));

        $this->assertSame('190.00', $xpath->query('//cac:TaxTotal/cbc:TaxAmount')->item(0)->textContent);
        $this->assertSame('1000.00', $xpath->query('//cbc:TaxExclusiveAmount')->item(0)->textContent);
        $this->assertSame('1190.00', $xpath->query('//cbc:TaxInclusiveAmount')->item(0)->textContent);
        $this->assertSame('1190.00', $xpath->query('//cbc:PayableAmount')->item(0)->textContent);
    }

    public function test_no_due_date_omits_the_element(): void
    {
        $invoice = new LocalizedInvoiceInput(
            invoiceNumber: 'INV-2026-002',
            issueDate: new \DateTimeImmutable('2026-01-15'),
            dueDate: null,
            currencyCode: 'EUR',
            seller: new LocalizedPartyInput('Acme GmbH', 'Hauptstraße 1', 'Berlin', '10115', 'DE'),
            buyer: new LocalizedPartyInput('Kunde AG', 'Nebenstraße 2', 'Munich', '80331', 'DE'),
            lineItems: [],
            totalNet: Money::zero('EUR'),
            totalTax: Money::zero('EUR'),
            totalGross: Money::zero('EUR'),
        );

        $xpath = $this->xpath((new XRechnungFormatter())->format($invoice));

        $this->assertSame(0, $xpath->query('//cbc:DueDate')->length);
    }

    public function test_special_characters_in_text_are_escaped(): void
    {
        $invoice = new LocalizedInvoiceInput(
            invoiceNumber: 'INV & 2026 <special>',
            issueDate: new \DateTimeImmutable('2026-01-15'),
            dueDate: null,
            currencyCode: 'EUR',
            seller: new LocalizedPartyInput('Acme GmbH', 'Hauptstraße 1', 'Berlin', '10115', 'DE'),
            buyer: new LocalizedPartyInput('Kunde AG', 'Nebenstraße 2', 'Munich', '80331', 'DE'),
            lineItems: [],
            totalNet: Money::zero('EUR'),
            totalTax: Money::zero('EUR'),
            totalGross: Money::zero('EUR'),
        );

        $xml = (new XRechnungFormatter())->format($invoice);
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML($xml));

        $xpath = $this->xpath($xml);
        $this->assertSame('INV & 2026 <special>', $xpath->query('//cbc:ID')->item(0)->textContent);
    }
}
