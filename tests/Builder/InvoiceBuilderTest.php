<?php

namespace SimplyIT\Fattura24SDK\Tests\Builder;

use PHPUnit\Framework\TestCase;
use SimplyIT\Fattura24SDK\Builder\InvoiceBuilder;
use SimplyIT\Fattura24SDK\Data\{
    DocumentType,
    RowData,
    DeliveryData,
    PaymentData
};

class InvoiceBuilderTest extends TestCase
{
    // =========================================================================
    // BASIC FUNCTIONALITY
    // =========================================================================

    public function testCreateReturnsBuilderInstance(): void
    {
        $builder = InvoiceBuilder::create();

        $this->assertInstanceOf(InvoiceBuilder::class, $builder);
    }

    public function testCreateWithDocumentType(): void
    {
        $builder = InvoiceBuilder::create(DocumentType::Ricevuta);

        $this->assertEquals(DocumentType::Ricevuta, $builder->getDocument()->documentType);
    }

    public function testFluentInterfaceReturnsBuilder(): void
    {
        $builder = InvoiceBuilder::create();

        $this->assertInstanceOf(InvoiceBuilder::class, $builder->totals(100, 100, 0));
        $this->assertInstanceOf(InvoiceBuilder::class, $builder->customer('Test'));
        $this->assertInstanceOf(InvoiceBuilder::class, $builder->row('Item', 1, 100, 22));
        $this->assertInstanceOf(InvoiceBuilder::class, $builder->payment('MP05', 'Bonifico Bancario'));
    }

    // =========================================================================
    // BUILD - SUCCESS CASES
    // =========================================================================

    public function testBuildBasicInvoice(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(122.00, 100.00, 22.00)
            ->customer('Test Customer', 'IT', 'test@example.com')
            ->fiscalCode('RSSMRA80A01F205X')
            ->payment('MP05', 'Bank transfer')
            ->row('Service', 1, 100.00, 22)
            ->build();

        $this->assertEquals(122.00, $invoice->document->total);
        $this->assertEquals(100.00, $invoice->document->totalWithoutTax);
        $this->assertEquals(22.00, $invoice->document->vatAmount);
        $this->assertEquals('Test Customer', $invoice->customer->customerName);
        $this->assertEquals('IT', $invoice->customer->customerCountry);
        $this->assertEquals('test@example.com', $invoice->customer->customerEmail);
        $this->assertEquals('RSSMRA80A01F205X', $invoice->customer->customerFiscalCode);
        $this->assertCount(1, $invoice->rows);
        $this->assertEquals('Service', $invoice->rows[0]->description);
    }

    public function testBuildWithMultipleRows(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(244.00, 200.00, 44.00)
            ->customer('Test')
            ->row('Item 1', 1, 100.00, 22)
            ->row('Item 2', 2, 50.00, 22)
            ->build();

        $this->assertCount(2, $invoice->rows);
        $this->assertEquals('Item 1', $invoice->rows[0]->description);
        $this->assertEquals(1, $invoice->rows[0]->qty);
        $this->assertEquals(100.00, $invoice->rows[0]->price);
        $this->assertEquals('Item 2', $invoice->rows[1]->description);
        $this->assertEquals(2, $invoice->rows[1]->qty);
        $this->assertEquals(50.00, $invoice->rows[1]->price);
    }

    public function testBuildWithZeroValues(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(100.00, 100.00, 0.00)
            ->customer('Test')
            ->row('Free item', 1, 0.00, 0, 'N4')
            ->build();

        $this->assertEquals(0.00, $invoice->document->vatAmount);
        $this->assertEquals(0.00, $invoice->rows[0]->price);
        $this->assertEquals('N4', $invoice->rows[0]->feVatNature);
    }

    public function testBuildWithNegativeValues(): void
    {
        $invoice = InvoiceBuilder::create(DocumentType::Ricevuta)
            ->totals(-122.00, -100.00, -22.00)
            ->customer('Test')
            ->row('Product return', 1, -100.00, 22)
            ->build();

        $this->assertEquals(-122.00, $invoice->document->total);
        $this->assertEquals(-100.00, $invoice->rows[0]->price);
    }

    public function testBuildWithDiscountRow(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(109.80, 90.00, 19.80)
            ->customer('Test')
            ->row('Product', 1, 100.00, 22)
            ->row('Discount -10%', 1, -10.00, 22)
            ->build();

        $this->assertCount(2, $invoice->rows);
        $this->assertEquals(-10.00, $invoice->rows[1]->price);
    }

    // =========================================================================
    // BUILD - VALIDATION FAILURES
    // =========================================================================

    public function testBuildThrowsExceptionIfCustomerNotSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer name is required');

        InvoiceBuilder::create()
            ->totals(122.00, 100.00, 22.00)
            ->row('Service', 1, 100.00, 22)
            ->build();
    }

    public function testBuildThrowsExceptionIfNoRows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one row is required');

        InvoiceBuilder::create()
            ->customer('Test Customer')
            ->totals(122.00, 100.00, 22.00)
            ->build();
    }

    public function testBuildThrowsExceptionIfRowsClearedAndNotReAdded(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one row is required');

        InvoiceBuilder::create()
            ->customer('Test')
            ->totals(122.00, 100.00, 22.00)
            ->row('Item', 1, 100, 22)
            ->clearRows()
            ->build();
    }

    // =========================================================================
    // CUSTOMER - LAZY INITIALIZATION & GUARDS
    // =========================================================================

    public function testGetCustomerReturnsNullWhenNotSet(): void
    {
        $builder = InvoiceBuilder::create();

        $this->assertNull($builder->getCustomer());
    }

    public function testFiscalCodeThrowsExceptionIfCustomerNotSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Customer must be set first');

        InvoiceBuilder::create()
            ->fiscalCode('RSSMRA80A01F205X');
    }

    public function testVatNumberThrowsExceptionIfCustomerNotSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Customer must be set first');

        InvoiceBuilder::create()
            ->vatNumber('12345678901');
    }

    public function testAddressThrowsExceptionIfCustomerNotSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Customer must be set first');

        InvoiceBuilder::create()
            ->address('Via Roma', 'Milano', '20100');
    }

    public function testPecThrowsExceptionIfCustomerNotSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Customer must be set first');

        InvoiceBuilder::create()
            ->pec('test@pec.it');
    }

    public function testSdiThrowsExceptionIfCustomerNotSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Customer must be set first');

        InvoiceBuilder::create()
            ->sdi('ABC1234');
    }

    public function testPhoneThrowsExceptionIfCustomerNotSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Customer must be set first');

        InvoiceBuilder::create()
            ->phone('+39123456789');
    }

    // =========================================================================
    // DOCUMENT METHODS
    // =========================================================================

    public function testTotals(): void
    {
        $builder = InvoiceBuilder::create()
            ->totals(122.00, 100.00, 22.00);

        $document = $builder->getDocument();

        $this->assertEquals(122.00, $document->total);
        $this->assertEquals(100.00, $document->totalWithoutTax);
        $this->assertEquals(22.00, $document->vatAmount);
    }

    public function testPayment(): void
    {
        $builder = InvoiceBuilder::create()
            ->payment('MP05', 'Bonifico bancario');

        $document = $builder->getDocument();

        $this->assertEquals('MP05', $document->fePaymentCode);
        $this->assertEquals('Bonifico bancario', $document->paymentMethodName);
    }

    public function testTemplate(): void
    {
        $builder = InvoiceBuilder::create()
            ->template(5);

        $this->assertEquals(5, $builder->getDocument()->idTemplate);
    }

    public function testNumerator(): void
    {
        $builder = InvoiceBuilder::create()
            ->numerator(3);

        $this->assertEquals(3, $builder->getDocument()->idNumerator);
    }

    public function testSendEmail(): void
    {
        $builder = InvoiceBuilder::create()
            ->sendEmail(false);

        $this->assertFalse($builder->getDocument()->sendEmail);

        $builder->sendEmail(true);

        $this->assertTrue($builder->getDocument()->sendEmail);
    }

    public function testCurrency(): void
    {
        $builder = InvoiceBuilder::create(DocumentType::Ricevuta)
            ->currency('USD');

        $this->assertEquals('USD', $builder->getDocument()->currency);
    }

    public function testNumber(): void
    {
        $builder = InvoiceBuilder::create()
            ->number('INV-2026-001');

        $this->assertEquals('INV-2026-001', $builder->getDocument()->number);
    }

    public function testObject(): void
    {
        $builder = InvoiceBuilder::create()
            ->object('Consulenza tecnica');

        $this->assertEquals('Consulenza tecnica', $builder->getDocument()->object);
    }

    public function testFootNotes(): void
    {
        $builder = InvoiceBuilder::create()
            ->footNotes('Grazie per il tuo ordine');

        $this->assertEquals('Grazie per il tuo ordine', $builder->getDocument()->footNotes);
    }

    // =========================================================================
    // CUSTOMER METHODS
    // =========================================================================

    public function testCustomerBasicInfo(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Mario Rossi', 'IT', 'mario@example.com');

        $customer = $builder->getCustomer();

        $this->assertNotNull($customer);
        $this->assertEquals('Mario Rossi', $customer->customerName);
        $this->assertEquals('IT', $customer->customerCountry);
        $this->assertEquals('mario@example.com', $customer->customerEmail);
    }

    public function testCustomerWithDefaultCountry(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Mario Rossi');

        $customer = $builder->getCustomer();

        $this->assertNotNull($customer);
        $this->assertEquals('IT', $customer->customerCountry);
    }

    public function testAddress(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test')
            ->address('Via Roma 123', 'Milano', '20100', 'MI');

        $customer = $builder->getCustomer();

        $this->assertEquals('Via Roma 123', $customer->customerAddress);
        $this->assertEquals('Milano', $customer->customerCity);
        $this->assertEquals('20100', $customer->customerPostcode);
        $this->assertEquals('MI', $customer->customerProvince);
    }

    public function testAddressWithoutProvince(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test')
            ->address('Via Roma 123', 'Milano', '20100');

        $customer = $builder->getCustomer();

        $this->assertEquals('Via Roma 123', $customer->customerAddress);
        $this->assertNull($customer->customerProvince);
    }

    public function testFiscalCode(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test')
            ->fiscalCode('RSSMRA80A01F205X');

        $this->assertEquals('RSSMRA80A01F205X', $builder->getCustomer()->customerFiscalCode);
    }

    public function testVatNumber(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test')
            ->vatNumber('12345678901');

        $this->assertEquals('12345678901', $builder->getCustomer()->customerVatCode);
    }

    public function testPec(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test')
            ->pec('test@pec.it');

        $this->assertEquals('test@pec.it', $builder->getCustomer()->feCustomerPec);
    }

    public function testSdi(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test')
            ->sdi('ABC1234');

        $this->assertEquals('ABC1234', $builder->getCustomer()->feDestinationCode);
    }

    public function testPhone(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test')
            ->phone('+39123456789');

        $this->assertEquals('+39123456789', $builder->getCustomer()->customerCellPhone);
    }

    // =========================================================================
    // ROW METHODS
    // =========================================================================

    public function testRow(): void
    {
        $builder = InvoiceBuilder::create()
            ->row('Consulenza tecnica', 2, 150.00, 22);

        $rows = $builder->getRows();

        $this->assertCount(1, $rows);
        $this->assertEquals('Consulenza tecnica', $rows[0]->description);
        $this->assertEquals(2, $rows[0]->qty);
        $this->assertEquals(150.00, $rows[0]->price);
        $this->assertEquals(22, $rows[0]->vatCode);
        $this->assertNull($rows[0]->feVatNature);
    }

    public function testRowWithVatNature(): void
    {
        $builder = InvoiceBuilder::create()
            ->row('Servizio esente', 1, 100.00, 0, 'N4');

        $rows = $builder->getRows();

        $this->assertEquals('N4', $rows[0]->feVatNature);
    }

    public function testRowWithDiscount(): void
    {
        $builder = InvoiceBuilder::create()
            ->rowWithDiscount('Prodotto Premium', 1, 100.00, 22, 10);

        $rows = $builder->getRows();

        $this->assertEquals(10, $rows[0]->discounts);
    }

    public function testRowWithDiscountAndNature(): void
    {
        $builder = InvoiceBuilder::create()
            ->rowWithDiscount('Prodotto esente', 1, 100.00, 0, 10, 'N4');

        $rows = $builder->getRows();

        $this->assertEquals(10, $rows[0]->discounts);
        $this->assertEquals('N4', $rows[0]->feVatNature);
    }

    public function testAddRowObject(): void
    {
        $row = new RowData('Custom Row', 2, 50.00, 10);

        $builder = InvoiceBuilder::create()
            ->addRow($row);

        $rows = $builder->getRows();

        $this->assertCount(1, $rows);
        $this->assertEquals('Custom Row', $rows[0]->description);
        $this->assertEquals(10, $rows[0]->vatCode);
    }

    public function testClearRows(): void
    {
        $builder = InvoiceBuilder::create()
            ->row('Item 1', 1, 100, 22)
            ->row('Item 2', 1, 50, 22);

        $this->assertCount(2, $builder->getRows());

        $builder->clearRows();

        $this->assertCount(0, $builder->getRows());
    }

    public function testGetRowCount(): void
    {
        $builder = InvoiceBuilder::create()
            ->row('Item 1', 1, 100, 22)
            ->row('Item 2', 1, 50, 22);

        $this->assertEquals(2, $builder->getRowCount());
    }

    // =========================================================================
    // DELIVERY METHODS
    // =========================================================================

    public function testDelivery(): void
    {
        $builder = InvoiceBuilder::create()
            ->delivery('ACME Warehouse', 'Via Magazzini 45', 'Milano', '20100', 'MI', 'IT');

        $delivery = $builder->getDelivery();

        $this->assertNotNull($delivery);
        $this->assertEquals('ACME Warehouse', $delivery->deliveryName);
        $this->assertEquals('Via Magazzini 45', $delivery->deliveryAddress);
        $this->assertEquals('Milano', $delivery->deliveryCity);
        $this->assertEquals('20100', $delivery->deliveryPostcode);
        $this->assertEquals('MI', $delivery->deliveryProvince);
        $this->assertEquals('IT', $delivery->deliveryCountry);
    }

    public function testDeliveryWithoutOptionalFields(): void
    {
        $builder = InvoiceBuilder::create()
            ->delivery('Warehouse', 'Via XXX', 'Roma', '00100');

        $delivery = $builder->getDelivery();

        $this->assertNotNull($delivery);
        $this->assertEquals('Warehouse', $delivery->deliveryName);
        $this->assertNull($delivery->deliveryProvince);
        $this->assertNull($delivery->deliveryCountry);
    }

    public function testSetDeliveryObject(): void
    {
        $delivery = new DeliveryData();
        $delivery->deliveryName = 'Custom Warehouse';
        $delivery->deliveryCity = 'Torino';

        $builder = InvoiceBuilder::create()
            ->setDelivery($delivery);

        $this->assertEquals('Custom Warehouse', $builder->getDelivery()->deliveryName);
        $this->assertEquals('Torino', $builder->getDelivery()->deliveryCity);
    }

    public function testBuildWithDelivery(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(100, 100, 0)
            ->customer('Test')
            ->delivery('Warehouse', 'Via XXX', 'Roma', '00100')
            ->row('Item', 1, 100, 0, 'N4')
            ->build();

        $this->assertNotNull($invoice->delivery);
        $this->assertEquals('Warehouse', $invoice->delivery->deliveryName);
        $this->assertEquals('Roma', $invoice->delivery->deliveryCity);
    }

    public function testBuildWithoutDelivery(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(100, 100, 0)
            ->customer('Test')
            ->row('Item', 1, 100, 0, 'N4')
            ->build();

        $this->assertNull($invoice->delivery);
    }

    // =========================================================================
    // PAYMENT METHODS
    // =========================================================================

    public function testAddPaymentInstallment(): void
    {
        $builder = InvoiceBuilder::create()
            ->addPaymentInstallment('2026-04-01', 100.00);

        $payments = $builder->getPayments();

        $this->assertCount(1, $payments);
        $this->assertEquals('2026-04-01', $payments[0]->date);
        $this->assertEquals(100.00, $payments[0]->amount);
        $this->assertFalse($payments[0]->paid);
    }

    public function testAddPaymentInstallmentPaid(): void
    {
        $builder = InvoiceBuilder::create()
            ->addPaymentInstallment('2026-04-01', 100.00, true);

        $payments = $builder->getPayments();

        $this->assertTrue($payments[0]->paid);
    }

    public function testAddMultiplePaymentInstallments(): void
    {
        $builder = InvoiceBuilder::create()
            ->addPaymentInstallment('2026-04-01', 100.00)
            ->addPaymentInstallment('2026-05-01', 100.00, true);

        $payments = $builder->getPayments();

        $this->assertCount(2, $payments);
        $this->assertEquals(100.00, $payments[0]->amount);
        $this->assertFalse($payments[0]->paid);
        $this->assertEquals(100.00, $payments[1]->amount);
        $this->assertTrue($payments[1]->paid);
    }

    public function testAddPaymentObject(): void
    {
        $payment = new PaymentData('2026-06-01', 500.00, true);

        $builder = InvoiceBuilder::create()
            ->addPayment($payment);

        $payments = $builder->getPayments();

        $this->assertCount(1, $payments);
        $this->assertEquals(500.00, $payments[0]->amount);
        $this->assertTrue($payments[0]->paid);
    }

    public function testClearPayments(): void
    {
        $builder = InvoiceBuilder::create()
            ->addPaymentInstallment('2026-04-01', 100)
            ->addPaymentInstallment('2026-05-01', 100);

        $this->assertCount(2, $builder->getPayments());

        $builder->clearPayments();

        $this->assertCount(0, $builder->getPayments());
    }

    public function testBuildWithPayments(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(200, 200, 0)
            ->customer('Test')
            ->payment('MP05', 'Bonifico Bancario')
            ->row('Item', 1, 200, 0, 'N4')
            ->addPaymentInstallment('2026-04-01', 100.00)
            ->addPaymentInstallment('2026-05-01', 100.00, true)
            ->build();

        $this->assertNotNull($invoice->payments);
        $this->assertIsArray($invoice->payments);
        $this->assertCount(2, $invoice->payments);
        $this->assertEquals(100.00, $invoice->payments[0]->amount);
        $this->assertFalse($invoice->payments[0]->paid);
        $this->assertTrue($invoice->payments[1]->paid);
    }

    public function testBuildWithoutPayments(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(100, 100, 0)
            ->customer('Test')
            ->row('Item', 1, 100, 0, 'N4')
            ->build();

        $this->assertEmpty($invoice->payments);
    }

    // =========================================================================
    // RESET
    // =========================================================================

    public function testReset(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('First Customer')
            ->fiscalCode('RSSMRA80A01F205X')
            ->totals(122, 100, 22)
            ->row('Item 1', 1, 100, 22)
            ->delivery('Warehouse', 'Via XXX', 'Roma', '00100')
            ->addPaymentInstallment('2026-04-01', 122);

        // Verify data is set
        $this->assertNotNull($builder->getCustomer());
        $this->assertEquals('First Customer', $builder->getCustomer()->customerName);
        $this->assertCount(1, $builder->getRows());
        $this->assertNotNull($builder->getDelivery());
        $this->assertCount(1, $builder->getPayments());

        // Reset
        $builder->reset();

        // Verify all cleared
        $this->assertNull($builder->getCustomer());
        $this->assertCount(0, $builder->getRows());
        $this->assertNull($builder->getDelivery());
        $this->assertCount(0, $builder->getPayments());
        $this->assertEquals(0.00, $builder->getDocument()->total);
    }

    public function testResetPreservesDocumentType(): void
    {
        $builder = InvoiceBuilder::create(DocumentType::Ricevuta)
            ->customer('Test')
            ->row('Item', 1, 100, 22);

        $this->assertEquals(DocumentType::Ricevuta, $builder->getDocument()->documentType);

        $builder->reset();

        $this->assertEquals(DocumentType::Ricevuta, $builder->getDocument()->documentType);
    }

    public function testResetAllowsReuse(): void
    {
        $builder = InvoiceBuilder::create();

        // First invoice
        $invoice1 = $builder
            ->customer('Customer 1')
            ->totals(122, 100, 22)
            ->row('Item 1', 1, 100, 22)
            ->build();

        $this->assertEquals('Customer 1', $invoice1->customer->customerName);

        // Reset and create second invoice
        $invoice2 = $builder
            ->reset()
            ->customer('Customer 2')
            ->totals(244, 200, 44)
            ->row('Item 2', 1, 200, 22)
            ->build();

        $this->assertEquals('Customer 2', $invoice2->customer->customerName);
        $this->assertEquals(244.00, $invoice2->document->total);
    }

    // =========================================================================
    // INSPECTION METHODS
    // =========================================================================

    public function testGetDocument(): void
    {
        $builder = InvoiceBuilder::create()
            ->totals(122, 100, 22);

        $document = $builder->getDocument();

        $this->assertEquals(122.00, $document->total);
        $this->assertEquals(DocumentType::FatturaElettronica, $document->documentType);
    }

    public function testGetCustomer(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test Customer', 'IT');

        $customer = $builder->getCustomer();

        $this->assertNotNull($customer);
        $this->assertEquals('Test Customer', $customer->customerName);
        $this->assertEquals('IT', $customer->customerCountry);
    }

    public function testGetRows(): void
    {
        $builder = InvoiceBuilder::create()
            ->row('Item 1', 1, 100, 22)
            ->row('Item 2', 1, 50, 22);

        $rows = $builder->getRows();

        $this->assertIsArray($rows);
        $this->assertCount(2, $rows);
        $this->assertInstanceOf(RowData::class, $rows[0]);
    }

    public function testGetDelivery(): void
    {
        $builder = InvoiceBuilder::create()
            ->delivery('Warehouse', 'Via XXX', 'Roma', '00100');

        $delivery = $builder->getDelivery();

        $this->assertInstanceOf(DeliveryData::class, $delivery);
        $this->assertEquals('Warehouse', $delivery->deliveryName);
    }

    public function testGetDeliveryReturnsNullWhenNotSet(): void
    {
        $builder = InvoiceBuilder::create();

        $this->assertNull($builder->getDelivery());
    }

    public function testGetPayments(): void
    {
        $builder = InvoiceBuilder::create()
            ->addPaymentInstallment('2026-04-01', 100);

        $payments = $builder->getPayments();

        $this->assertIsArray($payments);
        $this->assertCount(1, $payments);
        $this->assertInstanceOf(PaymentData::class, $payments[0]);
    }

    public function testGetPaymentsReturnsEmptyArrayWhenNotSet(): void
    {
        $builder = InvoiceBuilder::create();

        $payments = $builder->getPayments();

        $this->assertIsArray($payments);
        $this->assertEmpty($payments);
    }

    public function testInspectionMethodsAllowDirectModification(): void
    {
        $builder = InvoiceBuilder::create()
            ->customer('Test');

        // Direct modification via inspection
        $customer = $builder->getCustomer();
        $this->assertNotNull($customer);
        $customer->customerProvince = 'MI';

        $invoice = $builder
            ->totals(100, 100, 0)
            ->row('Item', 1, 100, 0, 'N4')
            ->build();

        $this->assertEquals('MI', $invoice->customer->customerProvince);
    }

    // =========================================================================
    // COMPLETE INVOICE EXAMPLES
    // =========================================================================

    public function testCompleteInvoiceWithAllFields(): void
    {
        $invoice = InvoiceBuilder::create()
            // Document
            ->totals(1220.00, 1000.00, 220.00)
            ->payment('MP05', 'Bonifico rateale')
            ->template(1)
            ->numerator(1)
            ->currency('EUR')
            ->number('INV-2026-001')
            ->object('Consulenza e prodotti')
            ->footNotes('Grazie per il tuo ordine')
            ->sendEmail(true)

            // Customer
            ->customer('ACME SRL', 'IT', 'info@acme.it')
            ->address('Via Roma 123', 'Milano', '20100', 'MI')
            ->vatNumber('11223344556')
            ->pec('acme@pec.it')
            ->sdi('ABC1234')
            ->phone('+39123456789')

            // Rows
            ->row('Product A', 2, 200.00, 22)
            ->row('Product B', 1, 300.00, 22)
            ->row('Service', 3, 100.00, 22)
            ->row('Discount -10%', 1, -50.00, 22)

            // Delivery
            ->delivery('ACME Warehouse', 'Via Magazzini 45', 'Milano', '20100', 'MI', 'IT')

            // Payment installments
            ->addPaymentInstallment('2026-04-01', 610.00)
            ->addPaymentInstallment('2026-05-01', 610.00)

            ->build();

        // Verify document
        $this->assertEquals(1220.00, $invoice->document->total);
        $this->assertEquals('MP05', $invoice->document->fePaymentCode);
        $this->assertEquals(1, $invoice->document->idTemplate);
        $this->assertEquals('INV-2026-001', $invoice->document->number);

        // Verify customer
        $this->assertEquals('ACME SRL', $invoice->customer->customerName);
        $this->assertEquals('11223344556', $invoice->customer->customerVatCode);
        $this->assertEquals('Via Roma 123', $invoice->customer->customerAddress);

        // Verify rows
        $this->assertCount(4, $invoice->rows);

        // Verify delivery
        $this->assertNotNull($invoice->delivery);
        $this->assertEquals('ACME Warehouse', $invoice->delivery->deliveryName);

        // Verify payments
        $this->assertNotNull($invoice->payments);
        $this->assertCount(2, $invoice->payments);
    }

    public function testMinimalInvoiceWithRequiredFieldsOnly(): void
    {
        $invoice = InvoiceBuilder::create()
            ->totals(122.00, 100.00, 22.00)
            ->customer('Mario Rossi')
            ->row('Service', 1, 100.00, 22)
            ->build();

        $this->assertEquals(122.00, $invoice->document->total);
        $this->assertEquals('Mario Rossi', $invoice->customer->customerName);
        $this->assertCount(1, $invoice->rows);
        $this->assertNull($invoice->delivery);
        $this->assertEmpty($invoice->payments);
    }
}