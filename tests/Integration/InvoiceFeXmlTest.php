<?php

namespace Davidakis\Fattura24SDK\Tests\Integration;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;
use Davidakis\Fattura24SDK\Data\CustomerData;
use Davidakis\Fattura24SDK\Data\DeliveryData;
use Davidakis\Fattura24SDK\Data\DocumentData;
use Davidakis\Fattura24SDK\Data\DocumentType;
use Davidakis\Fattura24SDK\Data\InvoiceData;
use Davidakis\Fattura24SDK\Data\PaymentData;
use Davidakis\Fattura24SDK\Data\RowData;
use Davidakis\Fattura24SDK\Xml\XmlGenerator;

/**
 * Generates a complete, realistic electronic invoice (FE) XML and saves it
 * to tests/Integration/output/invoice_fe.xml so it can be submitted
 * manually or programmatically to the Fattura24 API for format validation.
 *
 * The invoice covers the main FE scenarios used in the healthcare sector:
 *  - Exempt row (VatCode = 0, FeVatNature = N4, Art. 10 DPR 633/72)
 *  - Standard taxed row (VatCode = 22)
 *  - Delivery address
 *  - Split payment schedule
 *
 * Run with:
 *   ./vendor/bin/phpunit --group integration
 */
#[Group('integration')]
class InvoiceFeXmlTest extends TestCase
{
    private const OUTPUT_DIR  = __DIR__ . '/output';
    private const OUTPUT_FILE = self::OUTPUT_DIR . '/invoice_fe.xml';

    protected function setUp(): void
    {
        if (!is_dir(self::OUTPUT_DIR)) {
            mkdir(self::OUTPUT_DIR, 0755, true);
        }
    }

    public function testGeneratesCompleteElectronicInvoiceXml(): void
    {
        $this->expectOutputRegex('/.+/s');
        
        // ── Document ──────────────────────────────────────────────────────────
        $document = new DocumentData(
            documentType:             DocumentType::FatturaElettronica,
            total:                    1098.00,
            totalWithoutTax:          1000.00,
            vatAmount:                98.00,
            sendEmail:                false,
            fePaymentCode:            'MP05',
            paymentMethodName:        'Bonifico bancario',
            paymentMethodDescription: 'IBAN IT60 X054 2811 1010 0000 0123 456 — BIC: BLOPIT22'
        );

        $document->currency    = 'EUR';
        $document->object      = 'Prestazioni sanitarie — marzo 2025';
        $document->footNotes   = 'Operazione esente IVA ai sensi dell\'art. 10, n. 18, DPR 633/72.';
        $document->idNumerator = 567;

        // ── Customer ──────────────────────────────────────────────────────────
        $customer = new CustomerData('Mario Rossi');

        $customer->customerAddress    = 'Via Roma, 1';
        $customer->customerPostcode   = '00142';
        $customer->customerCity       = 'Roma';
        $customer->customerProvince   = 'RM';
        $customer->customerCountry    = 'IT';
        $customer->customerEmail      = 'mario.rossi@email.it';
        $customer->customerFiscalCode = 'RSSMRA80A01H501U';
        $customer->feCustomerPec      = 'mario.rossi@pec.it';
        $customer->feDestinationCode  = 'ABCDEFG';

        // ── Delivery ──────────────────────────────────────────────────────────
        $delivery = new DeliveryData();
        $delivery->deliveryName     = 'Mario Rossi';
        $delivery->deliveryAddress  = 'Via Napoli, 150';
        $delivery->deliveryPostcode = '00142';
        $delivery->deliveryCity     = 'Roma';
        $delivery->deliveryProvince = 'RM';
        $delivery->deliveryCountry  = 'IT';

        // ── Rows ──────────────────────────────────────────────────────────────

        // Row 1 — exempt (Art. 10, n. 18): medical visit
        $row1               = new RowData('Visita medica specialistica', 1, 300.00, 0);
        $row1->code         = 'VISIT-001';
        $row1->um           = 'pz';
        $row1->feVatNature  = 'N4'; // Esente art. 10

        // Row 2 — exempt: physiotherapy session
        $row2               = new RowData('Seduta di fisioterapia', 5, 80.00, 0);
        $row2->code         = 'FT-001';
        $row2->um           = 'sessione';
        $row2->feVatNature  = 'N4';

        // Row 3 — taxed 22%: medical device sale
        $row3                 = new RowData('Dispositivo medico ortopedico', 1, 180.00, 22);
        $row3->code           = 'DM-001';
        $row3->um             = 'pz';
        $row3->vatDescription = '22%';

        // ── Payments ──────────────────────────────────────────────────────────
        $payment1 = new PaymentData('2025-04-15', 549.00, false);
        $payment2 = new PaymentData('2025-05-15', 549.00, false);

        // ── Assemble ──────────────────────────────────────────────────────────
        $invoice = (new InvoiceData($document, $customer, [$row1, $row2, $row3]))
            ->withDelivery($delivery)
            ->withPayments([$payment1, $payment2]);

        $generator = new XmlGenerator();
        $xml       = $generator->fromInvoice($invoice);

        // ── Assertions ────────────────────────────────────────────────────────
        $this->assertNotEmpty($xml);
        $this->assertFalse(XmlGenerator::hasErrors($xml), XmlGenerator::getErrorMessage($xml));

        $dom = new DOMDocument();
        $dom->loadXML($xml);

        // Root structure
        $this->assertSame('Fattura24', $dom->documentElement->tagName);
        $this->assertSame(1, $dom->getElementsByTagName('Document')->length);

        // Document fields
        $this->assertNodeValue($dom, 'DocumentType',    'FE');
        $this->assertNodeValue($dom, 'Total',           '1098.00');
        $this->assertNodeValue($dom, 'TotalWithoutTax', '1000.00');
        $this->assertNodeValue($dom, 'SendEmail',       'false');
        $this->assertNodeValue($dom, 'FePaymentCode',   'MP05');
        $this->assertNodeValue($dom, 'Currency',        'EUR');
        $this->assertNodeValue($dom, 'IdNumerator',     '567');

        // Customer
        $this->assertNodeValue($dom, 'CustomerName',      'Mario Rossi');
        $this->assertNodeValue($dom, 'FeDestinationCode', 'ABCDEFG');
        $this->assertNodeValue($dom, 'FeCustomerPec',     'mario.rossi@pec.it');

        // Delivery
        $this->assertNodeValue($dom, 'DeliveryCity', 'Roma');

        // Rows
        $rows = $dom->getElementsByTagName('Row');
        $this->assertSame(3, $rows->length);

        // Row 1 — exempt
        $row1El = $rows->item(0);
        $this->assertSame('0',  $row1El->getElementsByTagName('VatCode')->item(0)->nodeValue);
        $this->assertSame('N4', $row1El->getElementsByTagName('FeVatNature')->item(0)->nodeValue);

        // Row 3 — taxed
        $row3El = $rows->item(2);
        $this->assertSame('22', $row3El->getElementsByTagName('VatCode')->item(0)->nodeValue);
        $this->assertSame(0,    $row3El->getElementsByTagName('FeVatNature')->length);

        // Payments
        $payments = $dom->getElementsByTagName('Payment');
        $this->assertSame(2, $payments->length);
        $this->assertSame('2025-04-15', $payments->item(0)->getElementsByTagName('Date')->item(0)->nodeValue);
        $this->assertSame('2025-05-15', $payments->item(1)->getElementsByTagName('Date')->item(0)->nodeValue);

        // Save to file for manual API submission
        file_put_contents(self::OUTPUT_FILE, $xml);

        $this->assertFileExists(self::OUTPUT_FILE);
        $this->assertGreaterThan(0, filesize(self::OUTPUT_FILE));

        echo "\n[integration] Invoice FE XML saved to: " . self::OUTPUT_FILE . "\n";
        echo $xml . "\n";
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    private function assertNodeValue(DOMDocument $dom, string $tag, string $expected): void
    {
        $nodes = $dom->getElementsByTagName($tag);
        $this->assertSame(1, $nodes->length, "Missing XML tag: <{$tag}>");
        $this->assertSame($expected, $nodes->item(0)->nodeValue, "Wrong value for <{$tag}>");
    }
}
