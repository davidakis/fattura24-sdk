# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.4] - 2026-04-09
## Vendor rename


## [2.1.3] - 2026-03-24
## Added
- **CF/PIVA validation**

## [2.1.2] - 2026-03-21
## Added
- **Logger methods**

## [2.1.1] - 2026-03-19
### Added
- **TestKeyResponse Object**
- minor bug fixing

## [2.1.0] - 2026-03-08

### Added
- **InvoiceBuilder**: Fluent interface for building invoices with chainable methods
 ```php
  $invoice = InvoiceBuilder::create()
      ->customer('Mario Rossi', 'IT', 'mario@example.com')
      ->fiscalCode('RSSMRA80A01F205X')
      ->totals(122.00, 100.00, 22.00)
      ->payment('MP05', 'Bonifico bancario')
      ->row('Consulenza tecnica', 1, 100.00, 22)
      ->build();
  ```    
- **XmlGenerator validation**: Required fields validated before XML generation
  * Customer name must not be empty
  * At least one row required
  * Row price must be set
  * Clear validation error messages

## [2.0.0] - 2026-02-22

### Added
- **Response objects**: `SaveDocumentResponse` instead of raw arrays
- **Validation**: Smart Italian tax code validation (P.IVA, CF, SDI, PEC)
  - Only validates when `customerCountry = 'IT'`
  - Length-only checks for safety (no complex checksums)
  - SDI accepts 6 or 7 characters
- **PdfManager**: Configurable PDF download with `setPdfDirectory()`
  - Save to file: `setPdfDirectory('/path/to/pdfs')`
  - Output to browser: `setPdfDirectory(null)` (checks for `headers_sent()`)
- **Magic setters**: Validated setters for `CustomerData` fiscal fields
  - Automatic sanitization (trim, uppercase)
  - Clear error messages on validation failure
- **PHPStan level 6**: Static analysis configuration included
- **PHP CS Fixer**: Code style enforcement with PSR-12 + strict rules
- **Composer scripts**: `composer test`, `composer cs-fix`, `composer stan`

### Changed
- **BREAKING**: `saveDocument()` now returns `SaveDocumentResponse` object instead of array
  - Old: `$result['docId']`
  - New: `$result->docId`
- **CustomerData**: Fiscal fields now use validated setters
  - `customerFiscalCode`, `customerVatCode`, `feCustomerPec`, `feDestinationCode`
  - Backward compatible via `__set()` magic method

### Fixed
- SDI validation now correctly accepts 6-7 characters (was hardcoded to 7)
- P.IVA validation skipped for non-IT countries

## [1.0.0] - 2025-01-15

### Added
- Initial release
- Complete Fattura24 API support
- Type-safe data objects
- XML generation with fluent interface
- HTTP client with error handling
- Response validation

[2.0.0]: https://github.com/davidakis/fattura24-sdk/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/davidakis/fattura24-sdk/releases/tag/v1.0.0
