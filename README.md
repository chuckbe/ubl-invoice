# UBL-Invoice

A modern object-oriented PHP library to **create** and **read** valid UBL and Peppol BIS 3.0 files. Please feel free to [contribute](https://github.com/num-num/ubl-invoice/pulls) if you are missing features or tags.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/num-num/ubl-invoice.svg?style=rounded-square)](https://packagist.org/packages/num-num/ubl-invoice)
[![Total Downloads](https://img.shields.io/packagist/dt/num-num/ubl-invoice.svg?style=rounded-square)](https://packagist.org/packages/num-num/ubl-invoice)


![Num•Num UBL Invoice](https://i.imgur.com/JPyFBYQ.png)

## About this fork (`chuckbe/ubl-invoice`)

This is a fork of [num-num/ubl-invoice](https://github.com/num-num/ubl-invoice), maintained by [Chuck](https://chuck.be) for **reading** incoming Peppol BIS Billing 3.0 documents. It fixes reading bugs that change amounts and payment data. Everything else is identical to upstream `v2.4.1`, and we'd like to see these fixes merged upstream.

The package name and namespaces are unchanged (`num-num/ubl-invoice`, `NumNum\UBL`), so it's a drop-in replacement. Fork versions add a fourth digit to the upstream version they're based on: `v2.4.1.1` is upstream `v2.4.1` plus the fixes below.

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/chuckbe/ubl-invoice" }
],
"require": {
    "num-num/ubl-invoice": "^2.4.1.1"
}
```

### Differences from upstream

Every fix has read tests in `tests/Read`.

#### 1. `getTaxTotal()` returns the invoice-currency TaxTotal, and the tax-currency one is available (BT-110/BT-111)
- **Problem.** When a document states a VAT accounting currency (`cbc:TaxCurrencyCode`, BT-6), it carries **two** `cac:TaxTotal` elements:
  - one in the invoice currency, **with** `cac:TaxSubtotal`s (BT-110);
  - one in the tax currency, **without** subtotals (BT-111).
  
  The UBL schema doesn't fix their order. Upstream reads only the first. When the tax-currency total comes first, `getTaxTotal()` returns an amount in the wrong currency and no VAT breakdown.
- **Why the fix is correct.** Peppol BIS 3.0 identifies the two totals by content, not by position:
  - **PEPPOL-EN16931-R053:** "Only one tax total with tax subtotals MUST be provided."
  - **PEPPOL-EN16931-R054:** "Only one tax total without tax subtotals MUST be provided when tax currency code is provided."
  - **PEPPOL-EN16931-R005:** the tax currency must differ from the invoice currency.
  
  So the TaxTotal with subtotals is always the invoice-currency total, and the other one is BT-111.
- **Change.**
  - `getTaxTotal()` returns the TaxTotal with subtotals. It falls back to the first one, as before, when none has subtotals.
  - The new `Invoice::getTaxCurrencyTaxTotal()` returns the other one, and `setTaxCurrencyTaxTotal()` writes it.
  - `TaxTotal::getCurrencyId()` / `setCurrencyId()` keep the `currencyID` of `cbc:TaxAmount`. When it's null, writing falls back to `Generator::$currencyID`, as before.

#### 2. `LegalMonetaryTotal::getPayableRoundingAmount()` returns the rounding amount (BT-114)
- **Problem.** Upstream fills `PayableRoundingAmount` from the `cbc:PayableAmount` element, so the getter returns the amount due.
- **Why the fix is correct.** BT-114 is its own element, `cbc:PayableRoundingAmount`. EN 16931 BR-CO-16 (*Amount due = Total with VAT − Paid amount + Rounding amount*) only holds with the real value.
- **Change.** It's read from `cbc:PayableRoundingAmount`, and is `null` when the element is absent.

#### 3. `PaymentMandate` reads and writes `PayerFinancialAccount` (BT-91)
- **Problem.** Upstream's `PaymentMandate` reads and writes a `cac:PayeeFinancialAccount`. That element doesn't exist inside `cac:PaymentMandate` in UBL 2.1, so the debited account of a SEPA direct debit was lost, and writing produced schema-invalid XML.
- **Why the fix is correct.** In UBL 2.1, `cac:PaymentMandate` contains `cbc:ID` (BT-89, the mandate reference) and `cac:PayerFinancialAccount/cbc:ID` (BT-91, the debited account).
- **Change.**
  - Based on upstream PR [#174](https://github.com/num-num/ubl-invoice/pull/174) by its author, plus fixes for the three blockers raised in that PR's review:
    - `PayerFinancialAccount` is registered in `Reader`. Without it, reading any direct-debit document threw a `TypeError`.
    - `PayerFinancialAccount::getId()` / `setId()` are nullable.
    - No empty `cbc:ID` is written.
  - **Breaking:** `PaymentMandate::get/setPayeeFinancialAccount()` are removed.

#### 4. Every `BillingReference` is read (BT-25/26)
- **Problem.** Upstream reads only the first `cac:BillingReference`, but the element has cardinality 0..n in UBL and Peppol BIS 3.0. A credit note can correct several invoices.
- **Change.** From upstream PR [#181](https://github.com/num-num/ubl-invoice/pull/181) by its author:
  - `getBillingReferences()` / `setBillingReferences()` / `addBillingReference()`;
  - `getBillingReference()` still returns the first one.

#### 5. Lists read from XML are indexed from 0
- **Problem.** `ReaderHelper::getArrayValue()` filtered the parent's children and kept each match's **position among all siblings** as its array key. The first billing reference could sit at key 14, so `$list[0]` and anything built on it (like `getBillingReference()`) returned null. It affected every list: payment means, allowance charges, TaxTotals, and so on.
- **Change.** The result goes through `array_values()`. Iteration order is unchanged; only the keys change.

## Installation and usage

This package is fully compatible with **PHP 8.x** and also supports **PHP 7.4**. You can install it using [Composer](https://www.getcomposer.org).
```zsh
$ composer require num-num/ubl-invoice
```

#### Creating UBL files

```php
$invoice = (new \NumNum\UBL\Invoice())
    ->setUBLVersionId('2.4')
    ->setId(123);
    // ... etc, all other props you need

$generator = new \NumNum\UBL\Generator();
$ublXml = $generator->invoice($invoice);
```

Please check some of the example code in the `tests/Write` folder to see how you can quickly create an UBL file and use all included properties.

#### Reading UBL files ✨

Need to quickly read UBL files? As of version 2.0, this library now supports UBL file reading. It's simple and easy to use:

```php
$ublReader = \NumNum\UBL\Reader::ubl();
$invoice = $ublReader->parse(file_get_contents($fileName));
var_dump($invoice); // An \NumNum\UBL\Invoice instance with filled properties!
```

Please check some additional example code in the `tests/Read` folder.

## Upgrading

If you are upgrading from version 1.x to 2.0, please check the [UPGRADING.md](UPGRADING.md) guide for breaking changes and migration instructions.

## Contributing - bug reporting

This library is **not 100% UBL/Peppol feature-complete**, in the sense that it doesn't (yet) support **all** UBL XML tags & functionality. "Yet" being the keyword, since this definitely is the long-term goal. However, **all common UBL tags that are required to create and read most common invoices and creditnotes** are present in the library. This includes tags for discounts, cash discounts, special vat rates, etc...

If you are missing functionality, please feel free to add it :-) Adding additional tags & attributes is *very* straight-forward. Check out [CONTRIBUTING.md](CONTRIBUTING.md) for more information.

Are you experiencing a bug? Please feel free to open an issue in the issue tracker!

## Documentation

- [Getting Started](docs/getting-started.md) - Quick start guide with installation and basic examples
- [Creating Invoices](docs/creating-invoices.md) - Detailed invoice creation guide
- [Creating Credit Notes](docs/creating-credit-notes.md) - Credit note creation guide
- [Reading UBL Files](docs/reading-ubl-files.md) - Parsing existing UBL documents
- [Advanced Features](docs/advanced-features.md) - Payment means, attachments, EN16931 compliance

For additional examples, check the unit tests in the `tests` folder.

## Changelog

Since version 2.0, all changelog information can be found on the [GitHub Releases](https://github.com/num-num/ubl-invoice/releases) page.
