<?php

namespace SimplyIT\Fattura24SDK\Tests\Xml;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\DeliveryData;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\DocumentType;
use SimplyIT\Fattura24SDK\Data\InvoiceData;
use SimplyIT\Fattura24SDK\Data\PaymentData;
use SimplyIT\Fattura24SDK\Data\RowData;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;
use SimplyIT\Fattura24SDK\Xml\XmlGenerator;

class XmlGeneratorTest extends TestCase
{
    private XmlGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new XmlGenerator();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function xpath(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        return new DOMXPath($dom);
    }

    private function xval(DOMXPath $xp, string $path): string
    {
        return $xp->query($path)->item(0)->nodeValue ?? '';
    }

    private function makeMinimalInvoice(): InvoiceData
    {
        $doc = new DocumentData(DocumentType::FatturaElettronica, 1220.0, 1000.0, 220.0, false, 'MP05', 'Bonifico', 'IBAN: IT00');
        $customer = new CustomerData('Acme S.r.l.');
        $row = new RowData('Visita medica', 1, 1000.0, 22);
        return new InvoiceData($doc, $customer, [$row]);
    }

    // -------------------------------------------------------------------------
    // Root structure
    // -------------------------------------------------------------------------

    public function testRootElementIsFattura24(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame(1, $xp->query('/Fattura24')->length);
    }

    public function testDocumentElementExists(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame(1, $xp->query('/Fattura24/Document')->length);
    }

    // -------------------------------------------------------------------------
    // Document fields
    // -------------------------------------------------------------------------

    public function testDocumentTypeIsWritten(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame('FE', $this->xval($xp, '//DocumentType'));
    }

    public function testTotalFieldsAreWritten(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame('1220.00', $this->xval($xp, '//Total'));
        $this->assertSame('1000.00', $this->xval($xp, '//TotalWithoutTax'));
        $this->assertSame('220.00', $this->xval($xp, '//VatAmount'));
    }

    public function testSendEmailIsFalseString(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame('false', $this->xval($xp, '//SendEmail'));
    }

    public function testOptionalDocumentFieldsOmittedWhenNull(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame(0, $xp->query('//Currency')->length);
        $this->assertSame(0, $xp->query('//Object')->length);
        $this->assertSame(0, $xp->query('//FootNotes')->length);
        $this->assertSame(0, $xp->query('//IdNumerator')->length);
    }

    public function testOptionalDocumentFieldsWrittenWhenSet(): void
    {
        $invoice = $this->makeMinimalInvoice();
        $invoice->document->currency    = 'EUR';
        $invoice->document->object      = 'Prestazione sanitaria';
        $invoice->document->idNumerator = 42;

        $xml = $this->generator->fromInvoice($invoice);
        $xp  = $this->xpath($xml);

        $this->assertSame('EUR',                   $this->xval($xp, '//Currency'));
        $this->assertSame('Prestazione sanitaria', $this->xval($xp, '//Object'));
        $this->assertSame('42',                    $this->xval($xp, '//IdNumerator'));
    }

    // -------------------------------------------------------------------------
    // Customer fields
    // -------------------------------------------------------------------------

    public function testCustomerNameIsWritten(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame('Acme S.r.l.', $this->xval($xp, '//CustomerName'));
    }

    public function testCustomerOptionalFieldsWrittenWhenSet(): void
    {
        $invoice = $this->makeMinimalInvoice();
        $invoice->customer->customerEmail      = 'info@acme.it';
        $invoice->customer->customerVatCode    = '12345678910';
        $invoice->customer->feCustomerPec      = 'acme@pec.it';
        $invoice->customer->feDestinationCode  = 'ABCDEFG';

        $xml = $this->generator->fromInvoice($invoice);
        $xp  = $this->xpath($xml);

        $this->assertSame('info@acme.it',  $this->xval($xp, '//CustomerEmail'));
        $this->assertSame('12345678910',   $this->xval($xp, '//CustomerVatCode'));
        $this->assertSame('acme@pec.it',   $this->xval($xp, '//FeCustomerPec'));
        $this->assertSame('ABCDEFG',       $this->xval($xp, '//FeDestinationCode'));
    }

    // -------------------------------------------------------------------------
    // Delivery section
    // -------------------------------------------------------------------------

    public function testDeliverySectionOmittedWhenNull(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame(0, $xp->query('//DeliveryName')->length);
    }

    public function testDeliverySectionWrittenWhenPresent(): void
    {
        $delivery              = new DeliveryData();
        $delivery->deliveryName    = 'Studio Medico Rossi';
        $delivery->deliveryCity    = 'Roma';
        $delivery->deliveryCountry = 'IT';

        $invoice = $this->makeMinimalInvoice()->withDelivery($delivery);
        $xml     = $this->generator->fromInvoice($invoice);
        $xp      = $this->xpath($xml);

        $this->assertSame('Studio Medico Rossi', $this->xval($xp, '//DeliveryName'));
        $this->assertSame('Roma',                $this->xval($xp, '//DeliveryCity'));
        $this->assertSame('IT',                  $this->xval($xp, '//DeliveryCountry'));
    }

    // -------------------------------------------------------------------------
    // Payments section
    // -------------------------------------------------------------------------

    public function testPaymentsSectionOmittedWhenEmpty(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame(0, $xp->query('//Payments')->length);
    }

    public function testSinglePaymentIsWritten(): void
    {
        $payment = new PaymentData('2025-03-31', 1220.0, true);
        $invoice = $this->makeMinimalInvoice()->withPayments([$payment]);
        $xml     = $this->generator->fromInvoice($invoice);
        $xp      = $this->xpath($xml);

        $this->assertSame(1,            $xp->query('//Payment')->length);
        $this->assertSame('2025-03-31', $this->xval($xp, '//Payment/Date'));
        $this->assertSame('1220.00',    $this->xval($xp, '//Payment/Amount'));
        $this->assertSame('true',       $this->xval($xp, '//Payment/Paid'));
    }

    public function testMultiplePaymentsAreWritten(): void
    {
        $invoice = $this->makeMinimalInvoice()->withPayments([
            new PaymentData('2025-03-31', 610.0),
            new PaymentData('2025-04-30', 610.0),
        ]);
        $xml = $this->generator->fromInvoice($invoice);
        $xp  = $this->xpath($xml);
        $this->assertSame(2, $xp->query('//Payment')->length);
    }

    // -------------------------------------------------------------------------
    // Rows section
    // -------------------------------------------------------------------------

    public function testSingleRowIsWritten(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);

        $this->assertSame(1,              $xp->query('//Row')->length);
        $this->assertSame('Visita medica', $this->xval($xp, '//Row/Description'));
        $this->assertSame('1',            $this->xval($xp, '//Row/Qty'));
        $this->assertSame('1000.00',    $this->xval($xp, '//Row/Price'));
        $this->assertSame('22',           $this->xval($xp, '//Row/VatCode'));
    }


    public function testFractionalQtyIsFormattedWithTwoDecimals(): void
    {
        $row     = new RowData('Prestazione a peso', 2.5, 40.00, 22);
        $doc     = new DocumentData(DocumentType::FatturaElettronica, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', '');
        $invoice = new InvoiceData($doc, new CustomerData('Test'), [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $xp  = $this->xpath($xml);
        $this->assertSame('2.50', $this->xval($xp, '//Row/Qty'));
    }


    public function testCicoriaAlKg(): void
    {
        // 500g di cicoria a 1.30€/kg → qty 0.5 kg, price 1.30€/kg, totale 0.65€
        // Tests: fractional qty (0.5 → '0.50'), small monetary amount (0.65 → '0.65')
        $row           = new RowData('Cicoria', 0.5, 1.30, 4);
        $row->um       = 'kg';

        $doc     = new DocumentData(DocumentType::Fattura, 0.68, 0.65, 0.03, false, 'MP01', 'Contanti', '');
        $invoice = new InvoiceData($doc, new CustomerData('Test'), [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $xp  = $this->xpath($xml);

        $this->assertSame('0.50', $this->xval($xp, '//Row/Qty'));
        $this->assertSame('kg',   $this->xval($xp, '//Row/Um'));
        $this->assertSame('1.30', $this->xval($xp, '//Row/Price'));
        $this->assertSame('4',    $this->xval($xp, '//Row/VatCode'));
        $this->assertSame('0.68', $this->xval($xp, '//Total'));
        $this->assertSame('0.65', $this->xval($xp, '//TotalWithoutTax'));
        $this->assertSame('0.03', $this->xval($xp, '//VatAmount'));
    }


    public function testCicoria634g(): void
    {
        // 634g di cicoria a 1.30€/kg
        // qty:   0.634 kg
        // price: 1.30€/kg (unit price)
        // subtotal (raw):  0.634 * 1.30 = 0.8242€  → rounded to 0.82€
        // vat 4%:          0.82 * 0.04  = 0.0328€  → rounded to 0.03€
        // total:           0.82 + 0.03  = 0.85€
        //
        // The SDK does not compute these values — the caller does.
        // This test verifies that formatQty and formatAmount handle
        // the serialization correctly for small fractional values.

        $qtyKg         = 0.634;
        $pricePerKg    = 1.30;
        $vatRate        = 4;

        $subtotal      = round($qtyKg * $pricePerKg, 2);   // 0.82
        $vatAmount     = round($subtotal * $vatRate / 100, 2); // 0.03
        $total         = round($subtotal + $vatAmount, 2);  // 0.85

        $row     = new RowData('Cicoria', $qtyKg, $pricePerKg, $vatRate);
        $row->um = 'kg';

        $doc     = new DocumentData(DocumentType::Fattura, $total, $subtotal, $vatAmount, false, 'MP01', 'Contanti', '');
        $invoice = new InvoiceData($doc, new CustomerData('Test'), [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $xp  = $this->xpath($xml);

        $this->assertSame('0.63',  $this->xval($xp, '//Row/Qty'));   // formatQty(0.634) = '0.63'
        $this->assertSame('kg',    $this->xval($xp, '//Row/Um'));
        $this->assertSame('1.30',  $this->xval($xp, '//Row/Price'));
        $this->assertSame('4',     $this->xval($xp, '//Row/VatCode'));
        $this->assertSame('0.85',  $this->xval($xp, '//Total'));
        $this->assertSame('0.82',  $this->xval($xp, '//TotalWithoutTax'));
        $this->assertSame('0.03',  $this->xval($xp, '//VatAmount'));
    }

    public function testIntegerQtyIsFormattedWithoutDecimals(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame('1', $this->xval($xp, '//Row/Qty'));
    }

    public function testOptionalRowFieldsOmittedWhenNull(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $xp  = $this->xpath($xml);
        $this->assertSame(0, $xp->query('//Code')->length);
        $this->assertSame(0, $xp->query('//FeVatNature')->length);
    }

    public function testOptionalRowFieldsWrittenWhenSet(): void
    {
        $row              = new RowData('Visita', 1, 100.0, 22);
        $row->code        = 'VISIT-001';
        $row->um          = 'pz';
        $row->idPdc       = 99;

        $doc     = new DocumentData(DocumentType::FatturaElettronica, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', '');
        $invoice = new InvoiceData($doc, new CustomerData('Test'), [$row]);
        $xml     = $this->generator->fromInvoice($invoice);
        $xp      = $this->xpath($xml);

        $this->assertSame('VISIT-001', $this->xval($xp, '//Code'));
        $this->assertSame('pz',        $this->xval($xp, '//Um'));
        $this->assertSame('99',        $this->xval($xp, '//IdPdc'));
    }

    // -------------------------------------------------------------------------
    // FeVatNature validation
    // -------------------------------------------------------------------------

    public function testFeVatNatureRequiredForFeWithZeroVat(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/FeVatNature/');

        $row     = new RowData('Prestazione esente', 1, 100.0, 0);
        $doc     = new DocumentData(DocumentType::FatturaElettronica, 100.0, 100.0, 0.0, false, 'MP05', 'Bonifico', '');
        $invoice = new InvoiceData($doc, new CustomerData('Test'), [$row]);

        $this->generator->fromInvoice($invoice);
    }

    public function testFeVatNatureInvalidCodeThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/not valid/i');

        $row               = new RowData('Prestazione esente', 1, 100.0, 0);
        $row->feVatNature  = 'N99'; // invalid
        $doc               = new DocumentData(DocumentType::FatturaElettronica, 100.0, 100.0, 0, false, 'MP05', 'Bonifico', '');
        $invoice           = new InvoiceData($doc, new CustomerData('Test'), [$row]);

        $this->generator->fromInvoice($invoice);
    }

    /** @dataProvider validNaturaCodeProvider */
    public function testFeVatNatureValidCodesAreAccepted(string $code): void
    {
        $row              = new RowData('Prestazione esente', 1, 100.0, 0);
        $row->feVatNature = $code;
        $doc              = new DocumentData(DocumentType::FatturaElettronica, 100.0, 100.0, 0, false, 'MP05', 'Bonifico', '');
        $invoice          = new InvoiceData($doc, new CustomerData('Test'), [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $xp  = $this->xpath($xml);

        $this->assertSame($code, $this->xval($xp, '//FeVatNature'));
    }

    public static function validNaturaCodeProvider(): array
    {
        return array_map(
            fn($c) => [$c],
            ['N1','N2.1','N2.2','N3.1','N3.2','N3.3','N3.4','N3.5',
             'N3.6','N4','N5','N6.1','N6.2','N6.3','N6.4','N6.5',
             'N6.6','N6.7','N6.8','N6.9','N7']
        );
    }

    public function testFeVatNatureNotRequiredForNonFeDocument(): void
    {
        // VatCode = 0 on a non-FE document should NOT trigger FeVatNature validation
        $row     = new RowData('Esente', 1, 100.0, 0);
        $doc     = new DocumentData(DocumentType::Fattura, 100.0, 100.0, 0.0, false, 'MP05', 'Bonifico', '');
        $invoice = new InvoiceData($doc, new CustomerData('Test'), [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $this->assertStringContainsString('<VatCode>0</VatCode>', $xml);
    }

    public function testFeVatNatureNotRequiredWhenVatCodeIsNotZero(): void
    {
        // VatCode = 22 on FE should NOT require FeVatNature
        $row     = new RowData('Prestazione', 1, 100.0, 22);
        $doc     = new DocumentData(DocumentType::FatturaElettronica, 122.0, 100.0, 22, false, 'MP05', 'Bonifico', '');
        $invoice = new InvoiceData($doc, new CustomerData('Test'), [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $this->assertStringContainsString('<VatCode>22</VatCode>', $xml);
    }

    // -------------------------------------------------------------------------
    // Customer XML
    // -------------------------------------------------------------------------

    public function testFromCustomerProducesValidXml(): void
    {
        $customer              = new CustomerData('Studio Rossi');
        $customer->customerEmail = 'info@studio.it';
        $xml = $this->generator->fromCustomer($customer);

        $xp = $this->xpath($xml);
        $this->assertSame('Studio Rossi', $this->xval($xp, '//CustomerName'));
        $this->assertSame('info@studio.it', $this->xval($xp, '//CustomerEmail'));
    }

    // -------------------------------------------------------------------------
    // Static helpers
    // -------------------------------------------------------------------------

    public function testHasErrorsReturnsFalseForValidXml(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $this->assertFalse(XmlGenerator::hasErrors($xml));
    }

    public function testHasErrorsReturnsTrueForErrorXml(): void
    {
        $errorXml = '<?xml version="1.0"?><Fattura24><DocumentError><ErrorMsg>test</ErrorMsg></DocumentError></Fattura24>';
        $this->assertTrue(XmlGenerator::hasErrors($errorXml));
    }

    public function testGetErrorMessageExtractsText(): void
    {
        $errorXml = '<?xml version="1.0"?><Fattura24><DocumentError><ErrorMsg>Errore di test</ErrorMsg></DocumentError></Fattura24>';
        $this->assertSame('Errore di test', XmlGenerator::getErrorMessage($errorXml));
    }

    public function testGetErrorMessageReturnsEmptyForValidXml(): void
    {
        $xml = $this->generator->fromInvoice($this->makeMinimalInvoice());
        $this->assertSame('', XmlGenerator::getErrorMessage($xml));
    }

    // -------------------------------------------------------------------------
    // CDATA
    // -------------------------------------------------------------------------

    public function testCustomerNameIsWrappedInCdata(): void
    {
        $customer = new CustomerData("Società & Partners <srl>");
        $doc      = new DocumentData(DocumentType::FatturaElettronica, 100.0, 100.0, 0, false, 'MP05', 'B', '');
        $row      = new RowData('Test', 1, 100.0, 0);
        $row->feVatNature = 'N4';
        $invoice  = new InvoiceData($doc, $customer, [$row]);

        $xml = $this->generator->fromInvoice($invoice);
        $this->assertStringContainsString('<![CDATA[', $xml);
        // The special characters should survive round-trip without entity encoding
        $this->assertStringContainsString("Società & Partners <srl>", $xml);
    }
}
