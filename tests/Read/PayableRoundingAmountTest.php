<?php

namespace NumNum\UBL\Tests\Read;

use NumNum\UBL\Invoice;
use NumNum\UBL\Reader;
use PHPUnit\Framework\TestCase;

/**
 * PayableRoundingAmount (BT-114) is read from its own element, not from PayableAmount.
 */
class PayableRoundingAmountTest extends TestCase
{
    public function testThePayableRoundingAmountIsReadFromItsOwnElement()
    {
        $invoice = $this->read(self::PARTIES, <<<XML
        <cbc:PayableRoundingAmount currencyID="EUR">0.01</cbc:PayableRoundingAmount>
        <cbc:PayableAmount currencyID="EUR">121.01</cbc:PayableAmount>
XML);

        $this->assertEquals(0.01, $invoice->getLegalMonetaryTotal()->getPayableRoundingAmount());
        $this->assertEquals(121.01, $invoice->getLegalMonetaryTotal()->getPayableAmount());
    }

    public function testThePayableRoundingAmountIsNullWithoutTheElement()
    {
        $invoice = $this->read(self::PARTIES, <<<XML
        <cbc:PayableAmount currencyID="EUR">121.00</cbc:PayableAmount>
XML);

        $this->assertNull($invoice->getLegalMonetaryTotal()->getPayableRoundingAmount());
    }

    private const PARTIES = <<<XML
    <cac:AccountingSupplierParty><cac:Party><cac:PartyName><cbc:Name>Supplier</cbc:Name></cac:PartyName></cac:Party></cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty><cac:Party><cac:PartyName><cbc:Name>Customer</cbc:Name></cac:PartyName></cac:Party></cac:AccountingCustomerParty>
XML;

    private function read(string $beforeTaxTotal, string $monetaryTotalExtra = ''): Invoice
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:ID>READ-1</cbc:ID>
    <cbc:IssueDate>2026-01-01</cbc:IssueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>EUR</cbc:DocumentCurrencyCode>
{$beforeTaxTotal}
    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="EUR">21.00</cbc:TaxAmount>
    </cac:TaxTotal>
    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="EUR">100.00</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="EUR">100.00</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EUR">121.00</cbc:TaxInclusiveAmount>
{$monetaryTotalExtra}
    </cac:LegalMonetaryTotal>
</Invoice>
XML;

        return Reader::ubl()->parse($xml);
    }
}
