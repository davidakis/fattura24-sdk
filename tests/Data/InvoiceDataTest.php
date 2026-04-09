<?php

namespace Davidakis\Fattura24SDK\Tests\Data;

use PHPUnit\Framework\TestCase;
use Davidakis\Fattura24SDK\Data\CustomerData;
use Davidakis\Fattura24SDK\Data\DeliveryData;
use Davidakis\Fattura24SDK\Data\DocumentData;
use Davidakis\Fattura24SDK\Data\DocumentType;
use Davidakis\Fattura24SDK\Data\InvoiceData;
use Davidakis\Fattura24SDK\Data\PaymentData;
use Davidakis\Fattura24SDK\Data\RowData;
use Davidakis\Fattura24SDK\Exceptions\ValidationException;

class InvoiceDataTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeDocument(): DocumentData
    {
        return new DocumentData(DocumentType::FatturaElettronica, 1220.0, 1000.0, 220.0, false, 'MP05', 'Bonifico', 'IBAN: IT00');
    }

    private function makeCustomer(): CustomerData
    {
        return new CustomerData('Acme S.r.l.');
    }

    private function makeRow(): RowData
    {
        return new RowData('Visita medica', 1, 1000.0, 22);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testConstructorSetsFields(): void
    {
        $row     = $this->makeRow();
        $invoice = new InvoiceData($this->makeDocument(), $this->makeCustomer(), [$row]);

        $this->assertInstanceOf(DocumentData::class, $invoice->document);
        $this->assertInstanceOf(CustomerData::class, $invoice->customer);
        $this->assertCount(1, $invoice->rows);
        $this->assertNull($invoice->delivery);
        $this->assertEmpty($invoice->payments);
    }

    public function testEmptyRowsThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        new InvoiceData($this->makeDocument(), $this->makeCustomer(), []);
    }

    public function testNonRowDataInRowsThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        new InvoiceData($this->makeDocument(), $this->makeCustomer(), ['not-a-row']);
    }

    // -------------------------------------------------------------------------
    // Fluent interface
    // -------------------------------------------------------------------------

    public function testWithDeliveryReturnsSelf(): void
    {
        $invoice  = new InvoiceData($this->makeDocument(), $this->makeCustomer(), [$this->makeRow()]);
        $delivery = new DeliveryData();
        $result   = $invoice->withDelivery($delivery);

        $this->assertSame($invoice, $result);
        $this->assertSame($delivery, $invoice->delivery);
    }

    public function testWithPaymentsReturnsSelf(): void
    {
        $invoice = new InvoiceData($this->makeDocument(), $this->makeCustomer(), [$this->makeRow()]);
        $payment = new PaymentData('2025-03-31', 1220.0, false);
        $result  = $invoice->withPayments([$payment]);

        $this->assertSame($invoice, $result);
        $this->assertCount(1, $invoice->payments);
    }

    public function testWithPaymentsThrowsOnInvalidEntry(): void
    {
        $this->expectException(ValidationException::class);
        $invoice = new InvoiceData($this->makeDocument(), $this->makeCustomer(), [$this->makeRow()]);
        $invoice->withPayments(['not-a-payment']);
    }

    public function testFluentChaining(): void
    {
        $delivery = new DeliveryData();
        $payment  = new PaymentData('2025-03-31', 1220.0);

        $invoice = (new InvoiceData($this->makeDocument(), $this->makeCustomer(), [$this->makeRow()]))
            ->withDelivery($delivery)
            ->withPayments([$payment]);

        $this->assertSame($delivery, $invoice->delivery);
        $this->assertCount(1, $invoice->payments);
    }

    // -------------------------------------------------------------------------
    // Multiple rows
    // -------------------------------------------------------------------------

    public function testMultipleRowsAreAccepted(): void
    {
        $rows    = [$this->makeRow(), $this->makeRow(), $this->makeRow()];
        $invoice = new InvoiceData($this->makeDocument(), $this->makeCustomer(), $rows);
        $this->assertCount(3, $invoice->rows);
    }
}
