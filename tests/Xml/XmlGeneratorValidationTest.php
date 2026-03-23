<?php

namespace SimplyIT\Fattura24SDK\Tests\Xml;

use PHPUnit\Framework\TestCase;
use SimplyIT\Fattura24SDK\Xml\XmlGenerator;
use SimplyIT\Fattura24SDK\Data\{
    DocumentData,
    DocumentType,
    CustomerData,
    RowData,
    InvoiceData
};
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;

class XmlGeneratorValidationTest extends TestCase
{
    private XmlGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new XmlGenerator();
    }

    // =========================================================================
    // VALID INVOICES (should pass)
    // =========================================================================

    public function testValidInvoicePassesValidation(): void
    {
        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 22.00;

        $customer = new CustomerData('Test Customer');
        $customer->setCustomerFiscalCode('RSSMRA80A01F205X'); // Codice fiscale italiano valido, per evitare che la normalizzazione lo consideri un cliente estero
        $customer->customerCountry = 'IT';

        $row = new RowData('Service', 1, 100.00, 22);

        $invoice = new InvoiceData($document, $customer, [$row]);

        // Should not throw
        $xml = $this->generator->fromInvoice($invoice);

        $this->assertStringContainsString('<Fattura24', $xml);
        $this->assertStringContainsString('<CustomerName><![CDATA[Test Customer]]></CustomerName>', $xml);
    }

    public function testValidInvoiceWithZeroValuesPassesValidation(): void
    {
        // Zero values are allowed
        $document = new DocumentData(DocumentType::FatturaElettronica, 100.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 0.00;

        $customer = new CustomerData('Test Customer');

        $row = new RowData('Free service', 1, 0.00, 0);
        $row->feVatNature = 'N4';

        $invoice = new InvoiceData($document, $customer, [$row]);

        // Should not throw
        $xml = $this->generator->fromInvoice($invoice);

        $this->assertStringContainsString('<Fattura24', $xml);
    }

    public function testValidInvoiceWithNegativeValuesPassesValidation(): void
    {
        // Negative values are allowed (credit notes)
        $document = new DocumentData(DocumentType::FatturaElettronica, -122.00);
        $document->totalWithoutTax = -100.00;
        $document->vatAmount = -22.00;

        $customer = new CustomerData('Test Customer');

        $row = new RowData('Product return', 1, -100.00, 22);

        $invoice = new InvoiceData($document, $customer, [$row]);

        // Should not throw
        $xml = $this->generator->fromInvoice($invoice);

        $this->assertStringContainsString('<Fattura24', $xml);
    }

    public function testValidInvoiceWithDiscountRowPassesValidation(): void
    {
        // Negative price is allowed (discount row)
        $document = new DocumentData(DocumentType::FatturaElettronica, 109.80);
        $document->totalWithoutTax = 90.00;
        $document->vatAmount = 19.80;

        $customer = new CustomerData('Test Customer');

        $rows = [];
        $rows[] = new RowData('Product', 1, 100.00, 22);
        $rows[] = new RowData('Discount', 1, -10.00, 22);

        $invoice = new InvoiceData($document, $customer, $rows);

        // Should not throw
        $xml = $this->generator->fromInvoice($invoice);

        $this->assertStringContainsString('<Fattura24', $xml);
    }

    public function testValidInvoiceWithMultipleRowsPassesValidation(): void
    {
        $document = new DocumentData(DocumentType::FatturaElettronica, 244.00);
        $document->totalWithoutTax = 200.00;
        $document->vatAmount = 44.00;

        $customer = new CustomerData('Test Customer');

        $rows = [];
        $rows[] = new RowData('Service 1', 1, 100.00, 22);
        $rows[] = new RowData('Service 2', 2, 50.00, 22);

        $invoice = new InvoiceData($document, $customer, $rows);

        // Should not throw
        $xml = $this->generator->fromInvoice($invoice);

        $this->assertStringContainsString('<Fattura24', $xml);
    }

    // =========================================================================
    // INVALID INVOICES - Customer name
    // =========================================================================

    public function testEmptyCustomerNameThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('CustomerName cannot be empty');

        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 22.00;

        $customer = new CustomerData(''); // Empty name!

        $row = new RowData('Service', 1, 100.00, 22);

        $invoice = new InvoiceData($document, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    public function testWhitespaceOnlyCustomerNameThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('CustomerName cannot be empty');

        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 22.00;

        $customer = new CustomerData('   '); // Whitespace only

        $row = new RowData('Service', 1, 100.00, 22);

        $invoice = new InvoiceData($document, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    // =========================================================================
    // INVALID INVOICES - Document totals
    // =========================================================================

    public function testMissingDocumentTotalThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Document total is required');

        $document = new DocumentData(DocumentType::FatturaElettronica, 0.00);
        // Simulate unset by creating object without setting total
        // (This is artificial - in real code, constructor sets it)
        $reflection = new \ReflectionClass($document);
        $property = $reflection->getProperty('total');
        $property->setAccessible(true);
        $property->setValue($document, null); // Set to 0, but total should be > 0 for a valid invoice

        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 22.00;

        $customer = new CustomerData('Test');

        $row = new RowData('Service', 1, 100.00, 22);

        $invoice = new InvoiceData($document, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    public function testMissingTotalWithoutTaxThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Document totalWithoutTax is required');

        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        // Simulate unset
        $reflection = new \ReflectionClass($document);
        $property = $reflection->getProperty('totalWithoutTax');
        $property->setAccessible(true);
        $property->setValue($document, null);

        $document->vatAmount = 22.00;

        $customer = new CustomerData('Test');

        $row = new RowData('Service', 1, 100.00, 22);

        $invoice = new InvoiceData($document, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    public function testMissingVatAmountThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Document vatAmount is required');

        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        $document->totalWithoutTax = 100.00;
        // Simulate unset
        $reflection = new \ReflectionClass($document);
        $property = $reflection->getProperty('vatAmount');
        $property->setAccessible(true);
        $property->setValue($document, null);

        $customer = new CustomerData('Test');

        $row = new RowData('Service', 1, 100.00, 22);

        $invoice = new InvoiceData($document, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    // =========================================================================
    // INVALID INVOICES - Row price
    // =========================================================================

    public function testMissingRowPriceThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Row #1: price is required');

        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 22.00;

        $customer = new CustomerData('Test');

        $row = new RowData('Service', 1, 100.00, 22);
        // Simulate unset price
        $reflection = new \ReflectionClass($row);
        $property = $reflection->getProperty('price');
        $property->setAccessible(true);
        $property->setValue($row, null);

        $invoice = new InvoiceData($document, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    public function testMissingPriceInSecondRowThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Row #2: price is required');

        $document = new DocumentData(DocumentType::FatturaElettronica, 244.00);
        $document->totalWithoutTax = 200.00;
        $document->vatAmount = 44.00;

        $customer = new CustomerData('Test');

        $rows = [];
        $rows[] = new RowData('Service 1', 1, 100.00, 22); // OK

        $row2 = new RowData('Service 2', 1, 100.00, 22);
        // Simulate unset price
        $reflection = new \ReflectionClass($row2);
        $property = $reflection->getProperty('price');
        $property->setAccessible(true);
        $property->setValue($row2, null);
        $rows[] = $row2;

        $invoice = new InvoiceData($document, $customer, $rows);

        $this->generator->fromInvoice($invoice);
    }

    public function testMissingPriceInThirdRowThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Row #3: price is required');

        $document = new DocumentData(DocumentType::FatturaElettronica, 366.00);
        $document->totalWithoutTax = 300.00;
        $document->vatAmount = 66.00;

        $customer = new CustomerData('Test');

        $rows = [];
        $rows[] = new RowData('Service 1', 1, 100.00, 22); // OK
        $rows[] = new RowData('Service 2', 1, 100.00, 22); // OK

        $row3 = new RowData('Service 3', 1, 100.00, 22);
        // Simulate unset price
        $reflection = new \ReflectionClass($row3);
        $property = $reflection->getProperty('price');
        $property->setAccessible(true);
        $property->setValue($row3, null);
        $rows[] = $row3;

        $invoice = new InvoiceData($document, $customer, $rows);

        $this->generator->fromInvoice($invoice);
    }

    // =========================================================================
    // INVALID INVOICES - Multiple errors
    // =========================================================================

    public function testMultipleValidationErrorsAreReported(): void
    {
        $this->expectException(ValidationException::class);

        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        // Missing totalWithoutTax
        $reflection = new \ReflectionClass($document);
        $property = $reflection->getProperty('totalWithoutTax');
        $property->setAccessible(true);
        $property->setValue($document, null);

        // Missing vatAmount
        $property = $reflection->getProperty('vatAmount');
        $property->setAccessible(true);
        $property->setValue($document, null);

        $customer = new CustomerData(''); // Empty name

        $row = new RowData('Service', 1, 100.00, 22);
        // Missing price
        $reflection = new \ReflectionClass($row);
        $property = $reflection->getProperty('price');
        $property->setAccessible(true);
        $property->setValue($row, null);

        $invoice = new InvoiceData($document, $customer, [$row]);

        try {
            $this->generator->fromInvoice($invoice);
        } catch (ValidationException $e) {
            // Should contain all error messages
            $message = $e->getMessage();

            $this->assertStringContainsString('Customer name is required', $message);
            $this->assertStringContainsString('Document totalWithoutTax is required', $message);
            $this->assertStringContainsString('Document vatAmount is required', $message);
            $this->assertStringContainsString('Row #1: price is required', $message);

            throw $e; // Re-throw for expectException
        }
    }

    public function testMultipleMissingRowPricesReportsAllRows(): void
    {
        $this->expectException(ValidationException::class);

        $document = new DocumentData(DocumentType::FatturaElettronica, 366.00);
        $document->totalWithoutTax = 300.00;
        $document->vatAmount = 66.00;

        $customer = new CustomerData('Test');

        $rows = [];

        // Row 1: OK
        $rows[] = new RowData('Service 1', 1, 100.00, 22);

        // Row 2: Missing price
        $row2 = new RowData('Service 2', 1, 100.00, 22);
        $reflection = new \ReflectionClass($row2);
        $property = $reflection->getProperty('price');
        $property->setAccessible(true);
        $property->setValue($row2, null);
        $rows[] = $row2;

        // Row 3: Missing price
        $row3 = new RowData('Service 3', 1, 100.00, 22);
        $reflection = new \ReflectionClass($row3);
        $property = $reflection->getProperty('price');
        $property->setAccessible(true);
        $property->setValue($row3, null);
        $rows[] = $row3;

        $invoice = new InvoiceData($document, $customer, $rows);

        try {
            $this->generator->fromInvoice($invoice);
        } catch (ValidationException $e) {
            $message = $e->getMessage();

            $this->assertStringContainsString('Row #2: price is required', $message);
            $this->assertStringContainsString('Row #3: price is required', $message);
            $this->assertStringNotContainsString('Row #1: price is required', $message);

            throw $e;
        }
    }

    // =========================================================================
    // EDGE CASES
    // =========================================================================

    public function testInvoiceWithOnlyOptionalFieldsMissingIsValid(): void
    {
        // Only required fields set, all optional fields null/empty
        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 22.00;
        // All other fields null (optional)

        $customer = new CustomerData('Test Customer');
        // All other fields null (optional)

        $row = new RowData('Service', 1, 100.00, 22);
        // All other fields null (optional)

        $invoice = new InvoiceData($document, $customer, [$row]);

        // Should not throw
        $xml = $this->generator->fromInvoice($invoice);

        $this->assertStringContainsString('<Fattura24', $xml);
    }

    public function testInvoiceWithAllFieldsSetIsValid(): void
    {
        // All fields set (required + optional)
        $document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 22.00;
        $document->currency = 'EUR';
        $document->footNotes = 'Test notes';
        $document->idTemplate = 1;
        $document->idNumerator = 1;

        $customer = new CustomerData('Test Customer');
        $customer->customerAddress = 'Test Street 123';
        $customer->customerCity = 'Test City';
        $customer->customerPostcode = '12345';
        $customer->customerCountry = 'IT';
        $customer->customerEmail = 'test@example.com';
        $customer->customerFiscalCode = 'RSSMRA80A01F205X';

        $row = new RowData('Service', 1, 100.00, 22);
        $row->code = 'SRV001';
        $row->um = 'hour';

        $invoice = new InvoiceData($document, $customer, [$row]);

        // Should not throw
        $xml = $this->generator->fromInvoice($invoice);

        $this->assertStringContainsString('<Fattura24', $xml);
        $this->assertStringContainsString('RSSMRA80A01F205X', $xml);
        $this->assertStringContainsString('Test notes', $xml);
    }

    // =========================================================================
    // EXISTING VALIDATION (FeVatNature) still works
    // =========================================================================

    public function testZeroVatWithoutNaturaStillThrowsException(): void
    {
        // Existing FeVatNature validation should still work
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('FeVatNature is required');

        $document = new DocumentData(DocumentType::FatturaElettronica, 100.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 0.00;

        $customer = new CustomerData('Test');

        $row = new RowData('Service', 1, 100.00, 0);
        // Missing feVatNature!

        $invoice = new InvoiceData($document, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    public function testZeroVatWithInvalidNaturaStillThrowsException(): void
    {
        // Existing FeVatNature validation should still work
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('FeVatNature value \'INVALID\' is not valid');

        $document = new DocumentData(DocumentType::FatturaElettronica, 100.00);
        $document->totalWithoutTax = 100.00;
        $document->vatAmount = 0.00;

        $customer = new CustomerData('Test');

        $row = new RowData('Service', 1, 100.00, 0);
        $row->feVatNature = 'INVALID'; // Invalid code!

        $invoice = new InvoiceData($document, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    // -------------------------------------------------------------------------
    // FE — validazione P.IVA / Codice Fiscale
    // -------------------------------------------------------------------------

    public function testFeDocumentWithoutVatCodeAndFiscalCodeThrowsException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/CustomerVatCode or CustomerFiscalCode/');

        $doc      = new DocumentData(DocumentType::FatturaElettronica, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', '');
        $customer = new CustomerData('Acme Srl');
        $customer->customerCountry = 'IT';
        $row      = new RowData('Servizio', 1, 100.0, 22);
        $invoice  = new InvoiceData($doc, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }

    public function testFeDocumentWithVatCodeOnlyPassesValidation(): void
    {
        $doc      = new DocumentData(DocumentType::FatturaElettronica, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', '');
        $customer = new CustomerData('Acme Srl');
        $customer->customerVatCode = '12345678910';
        $row      = new RowData('Servizio', 1, 100.0, 22);
        $invoice  = new InvoiceData($doc, $customer, [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $this->assertFalse(XmlGenerator::hasErrors($xml));
    }

    public function testFeDocumentWithFiscalCodeOnlyPassesValidation(): void
    {
        $doc      = new DocumentData(DocumentType::FatturaElettronica, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', '');
        $customer = new CustomerData('Mario Rossi');
        $customer->customerFiscalCode = 'RSSMRA80A01H501U';
        $row      = new RowData('Servizio', 1, 100.0, 22);
        $invoice  = new InvoiceData($doc, $customer, [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $this->assertFalse(XmlGenerator::hasErrors($xml));
    }

    public function testFeDocumentWithBothVatCodeAndFiscalCodePassesValidation(): void
    {
        $doc      = new DocumentData(DocumentType::FatturaElettronica, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', '');
        $customer = new CustomerData('Mario Rossi');
        $customer->customerVatCode    = '12345678910';
        $customer->customerFiscalCode = 'RSSMRA80A01H501U';
        $row      = new RowData('Servizio', 1, 100.0, 22);
        $invoice  = new InvoiceData($doc, $customer, [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $this->assertFalse(XmlGenerator::hasErrors($xml));
    }

    public function testNonFeDocumentWithoutVatCodeAndFiscalCodePassesValidation(): void
    {
        // Per documenti non FE il check P.IVA/CF non si applica
        $doc      = new DocumentData(DocumentType::Fattura, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', '');
        $customer = new CustomerData('Mario Rossi');
        // nessun vatCode né fiscalCode
        $row      = new RowData('Servizio', 1, 100.0, 22);
        $invoice  = new InvoiceData($doc, $customer, [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $this->assertFalse(XmlGenerator::hasErrors($xml));
    }

    public function testFeValidationErrorsAccumulateWithOtherErrors(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/CustomerVatCode or CustomerFiscalCode/');

        $doc      = new DocumentData(DocumentType::FatturaElettronica, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', '');
        $customer = new CustomerData('Acme Srl');
        $customer->customerCountry = 'IT';

        // Riga con prezzo zero per simulare un caso limite senza violare il tipo
        $row      = new RowData('Servizio', 1, 0.0, 22);
        $invoice  = new InvoiceData($doc, $customer, [$row]);

        $this->generator->fromInvoice($invoice);
    }
}