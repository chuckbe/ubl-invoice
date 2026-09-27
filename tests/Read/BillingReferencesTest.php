<?php

namespace NumNum\UBL\Tests\Read;

use NumNum\UBL\Invoice;
use NumNum\UBL\Reader;
use PHPUnit\Framework\TestCase;

/**
 * BillingReference has cardinality 0..n (BT-25/26).
 */
class BillingReferencesTest extends TestCase
{
    public function testEveryBillingReferenceIsRead()
    {
        $invoice = $this->read(<<<XML
    <cac:BillingReference>
        <cac:InvoiceDocumentReference><cbc:ID>INV-1</cbc:ID><cbc:IssueDate>2025-12-01</cbc:IssueDate></cac:InvoiceDocumentReference>
    </cac:BillingReference>
    <cac:BillingReference>
        <cac:InvoiceDocumentReference><cbc:ID>INV-2</cbc:ID></cac:InvoiceDocumentReference>
    </cac:BillingReference>
XML . self::PARTIES);

        $references = $invoice->getBillingReferences();

        $this->assertCount(2, $references);
        $this->assertSame('INV-1', $references[0]->getInvoiceDocumentReference()->getOriginalInvoiceId());
        $this->assertSame('2025-12-01', $references[0]->getInvoiceDocumentReference()->getIssueDate()->format('Y-m-d'));
        $this->assertSame('INV-2', $references[1]->getInvoiceDocumentReference()->getOriginalInvoiceId());
    }

    public function testTheSingleReferenceGetterReturnsTheFirstReference()
    {
        $invoice = $this->read(<<<XML
    <cac:BillingReference>
        <cac:InvoiceDocumentReference><cbc:ID>INV-1</cbc:ID></cac:InvoiceDocumentReference>
    </cac:BillingReference>
XML . self::PARTIES);

        $this->assertSame('INV-1', $invoice->getBillingReference()->getInvoiceDocumentReference()->getOriginalInvoiceId());
    }

    public function testADocumentWithoutBillingReferencesHasNone()
    {
        $invoice = $this->read(self::PARTIES);

        $this->assertSame([], $invoice->getBillingReferences());
        $this->assertNull($invoice->getBillingReference());
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
