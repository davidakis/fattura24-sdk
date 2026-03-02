# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[2.0.0]: https://github.com/simplyit/fattura24-sdk/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/simplyit/fattura24-sdk/releases/tag/v1.0.0
