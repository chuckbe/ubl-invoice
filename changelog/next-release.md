# Next release

## Added

- Add support for multiple billing references on invoices and credit notes while preserving the single-reference API
- Add `Invoice::getTaxCurrencyTaxTotal()` (BT-111): the TaxTotal without subtotals that accompanies a TaxCurrencyCode (BT-6)
- Add `TaxTotal::getCurrencyId()`, read from and written to the TaxAmount `currencyID`
- Add `PaymentMandate::getPayerFinancialAccount()` (BT-91, the debited account for SEPA direct debit)

## Fixed

- Fix `getTaxTotal()` returning the tax-currency TaxTotal when it precedes the invoice-currency one; it now returns the TaxTotal with subtotals (Peppol R053/R054)
- Fix `LegalMonetaryTotal::getPayableRoundingAmount()` returning the PayableAmount (it was read from the wrong element)
- Fix lists read from XML (`getBillingReferences()`, `getPaymentMeans()`, …) keeping sibling positions as keys; they are now indexed from 0
- `PaymentMandate` no longer reads or writes a `PayeeFinancialAccount`, which UBL doesn't define there (**breaking** for callers of `get/setPayeeFinancialAccount()` on a mandate)

- Fix TypeError: Change setter types to nullable in reference classes to handle empty XML elements gracefully during parsing
  - `OrderReference::setId()` now accepts `?string`
  - `ProjectReference::setId()` now accepts `?string`
  - `ContractDocumentReference::setId()` now accepts `?string`
  - `InvoiceDocumentReference::setOriginalInvoiceId()` now accepts `?string`

### Maintenance

- Update dependency constraints to support Doctrine Collections 3.x
