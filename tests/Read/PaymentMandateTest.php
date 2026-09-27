<?php

namespace NumNum\UBL\Tests\Read;

use NumNum\UBL\Invoice;
use NumNum\UBL\PayerFinancialAccount;
use NumNum\UBL\PaymentMandate;
use NumNum\UBL\Reader;
use NumNum\UBL\Schema;
use Sabre\Xml\Writer;
use PHPUnit\Framework\TestCase;

/**
 * PaymentMandate: BT-89 mandate reference and BT-91 debited account (PayerFinancialAccount).
 */
class PaymentMandateTest extends TestCase
{
    public function testTheMandateReferenceAndDebitedAccountAreRead()
    {
        $invoice = $this->read(self::PARTIES . <<<XML
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode>59</cbc:PaymentMeansCode>
        <cac:PaymentMandate>
            <cbc:ID>MANDATE-42</cbc:ID>
            <cac:PayerFinancialAccount><cbc:ID>BE68539007547034</cbc:ID></cac:PayerFinancialAccount>
        </cac:PaymentMandate>
    </cac:PaymentMeans>
XML);

        $mandate = $invoice->getPaymentMeans()[0]->getPaymentMandate();

        $this->assertSame('MANDATE-42', $mandate->getId());
        $this->assertSame('BE68539007547034', $mandate->getPayerFinancialAccount()->getId());
    }

    public function testAMandateWithoutADebitedAccountIsRead()
    {
        $invoice = $this->read(self::PARTIES . <<<XML
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode>59</cbc:PaymentMeansCode>
        <cac:PaymentMandate><cbc:ID>MANDATE-42</cbc:ID></cac:PaymentMandate>
    </cac:PaymentMeans>
XML);

        $mandate = $invoice->getPaymentMeans()[0]->getPaymentMandate();

        $this->assertSame('MANDATE-42', $mandate->getId());
        $this->assertNull($mandate->getPayerFinancialAccount());
    }

    public function testADebitedAccountWithoutAnIdIsReadAndWrittenWithoutAnEmptyId()
    {
        $invoice = $this->read(self::PARTIES . <<<XML
    <cac:PaymentMeans>
        <cbc:PaymentMeansCode>59</cbc:PaymentMeansCode>
        <cac:PaymentMandate><cac:PayerFinancialAccount/></cac:PaymentMandate>
    </cac:PaymentMeans>
XML);

        $account = $invoice->getPaymentMeans()[0]->getPaymentMandate()->getPayerFinancialAccount();

        $writer = new Writer();
        $writer->openMemory();
        $writer->write([Schema::CAC . 'PayerFinancialAccount' => $account]);

        $this->assertNull($account->getId());
        $this->assertStringNotContainsString('ID', $writer->outputMemory());
    }

    public function testAMandateWritesThePayerFinancialAccount()
    {
        $mandate = (new PaymentMandate())
            ->setId('MANDATE-42')
            ->setPayerFinancialAccount((new PayerFinancialAccount())->setId('BE68539007547034'));

        $writer = new Writer();
        $writer->openMemory();
        $writer->write([Schema::CAC . 'PaymentMandate' => $mandate]);
        $xml = $writer->outputMemory();

        $this->assertStringContainsString('PayerFinancialAccount', $xml);
        $this->assertStringContainsString('>BE68539007547034<', $xml);
        $this->assertStringNotContainsString('PayeeFinancialAccount', $xml);
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
