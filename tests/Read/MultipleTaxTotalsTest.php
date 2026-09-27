<?php

namespace NumNum\UBL\Tests\Read;

use NumNum\UBL\Invoice;
use NumNum\UBL\Reader;
use NumNum\UBL\Schema;
use NumNum\UBL\TaxTotal;
use Sabre\Xml\Writer;
use PHPUnit\Framework\TestCase;

/**
 * Peppol R053/R054: with a tax currency (BT-6) a document carries a TaxTotal
 * with subtotals (BT-110, invoice currency) and one without (BT-111, tax
 * currency), in any order.
 */
class MultipleTaxTotalsTest extends TestCase
{
    private const INVOICE_CURRENCY_TOTAL = <<<XML
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="USD">21.00</cbc:TaxAmount>
        <cac:TaxSubtotal>
            <cbc:TaxableAmount currencyID="USD">100.00</cbc:TaxableAmount>
            <cbc:TaxAmount currencyID="USD">21.00</cbc:TaxAmount>
            <cac:TaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>21</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:TaxCategory>
        </cac:TaxSubtotal>
    </cac:TaxTotal>
XML;

    private const TAX_CURRENCY_TOTAL = <<<XML
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="EUR">19.00</cbc:TaxAmount>
    </cac:TaxTotal>
XML;

    public function testTheTaxTotalWithSubtotalsIsTheInvoiceTotalWhenTheTaxCurrencyTotalComesFirst()
    {
        $invoice = $this->read(self::TAX_CURRENCY_TOTAL . self::INVOICE_CURRENCY_TOTAL);

        $this->assertEquals(21.00, $invoice->getTaxTotal()->getTaxAmount());
        $this->assertSame('USD', $invoice->getTaxTotal()->getCurrencyId());
        $this->assertCount(1, $invoice->getTaxTotal()->getTaxSubTotals());
        $this->assertEquals(19.00, $invoice->getTaxCurrencyTaxTotal()->getTaxAmount());
        $this->assertSame('EUR', $invoice->getTaxCurrencyTaxTotal()->getCurrencyId());
    }

    public function testTheTaxTotalWithSubtotalsIsTheInvoiceTotalWhenItComesFirst()
    {
        $invoice = $this->read(self::INVOICE_CURRENCY_TOTAL . self::TAX_CURRENCY_TOTAL);

        $this->assertEquals(21.00, $invoice->getTaxTotal()->getTaxAmount());
        $this->assertEquals(19.00, $invoice->getTaxCurrencyTaxTotal()->getTaxAmount());
    }

    public function testASingleTaxTotalHasNoTaxCurrencyTotal()
    {
        $invoice = $this->read(self::INVOICE_CURRENCY_TOTAL);

        $this->assertEquals(21.00, $invoice->getTaxTotal()->getTaxAmount());
        $this->assertNull($invoice->getTaxCurrencyTaxTotal());
    }

    public function testASingleTaxTotalWithoutSubtotalsIsStillTheInvoiceTotal()
    {
        $invoice = $this->read(self::TAX_CURRENCY_TOTAL);

        $this->assertEquals(19.00, $invoice->getTaxTotal()->getTaxAmount());
        $this->assertNull($invoice->getTaxCurrencyTaxTotal());
    }

    public function testATaxTotalWritesItsOwnCurrency()
    {
        $taxTotal = (new TaxTotal())->setTaxAmount(19.00)->setCurrencyId('EUR');

        $writer = new Writer();
        $writer->openMemory();
        $writer->write([Schema::CAC . 'TaxTotal' => $taxTotal]);

        $xml = $writer->outputMemory();

        $this->assertStringContainsString('TaxAmount currencyID="EUR"', $xml);
        $this->assertStringContainsString('>19.00<', $xml);
    }

    private function read(string $taxTotals): Invoice
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:ID>TAX-CUR-1</cbc:ID>
    <cbc:IssueDate>2026-01-01</cbc:IssueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>USD</cbc:DocumentCurrencyCode>
    <cbc:TaxCurrencyCode>EUR</cbc:TaxCurrencyCode>
    <cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Supplier</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty><cac:Party><cac:PartyName><cbc:Name>Customer</cbc:Name></cac:PartyName></cac:Party></cac:AccountingCustomerParty>
{$taxTotals}
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="USD">100.00</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="USD">100.00</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="USD">121.00</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="USD">121.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    <cac:InvoiceLine>
        <cbc:ID>1</cbc:ID>
        <cbc:InvoicedQuantity unitCode="C62">1</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="USD">100.00</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Name>Item</cbc:Name>
            <cac:ClassifiedTaxCategory><cbc:ID>S</cbc:ID><cbc:Percent>21</cbc:Percent><cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme></cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price><cbc:PriceAmount currencyID="USD">100.00</cbc:PriceAmount></cac:Price>
    </cac:InvoiceLine>
</Invoice>
XML;

        return Reader::ubl()->parse($xml);
    }
}
