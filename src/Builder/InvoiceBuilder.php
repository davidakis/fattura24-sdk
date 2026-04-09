<?php

namespace Davidakis\Fattura24SDK\Builder;

use InvalidArgumentException;
use LogicException;
use Davidakis\Fattura24SDK\Data\{
    CustomerData,
    DeliveryData,
    DocumentData,
    DocumentType,
    InvoiceData,
    PaymentData,
    RowData
};

/**
 * InvoiceBuilder - Fluent interface for building invoices
 *
 * Provides a concise, chainable API for creating InvoiceData objects.
 * Does NOT perform calculations - all values must be provided explicitly.
 *
 * Example:
 * ```php
 * $invoice = InvoiceBuilder::create()
 *     ->customer('Mario Rossi', 'IT', 'mario@example.com')
 *     ->fiscalCode('RSSMRA80A01F205X')
 *     ->totals(122.00, 100.00, 22.00)
 *     ->payment('MP05', 'Bonifico bancario')
 *     ->row('Consulenza tecnica', 1, 100.00, 22)
 *     ->build();
 * ```
 */
class InvoiceBuilder
{
    private DocumentData $document;
    private ?CustomerData $customer = null;
    private array $rows = [];
    private ?DeliveryData $delivery = null;
    private array $payments = [];

    // =========================================================================
    // FACTORY
    // =========================================================================

    /**
     * Create a new InvoiceBuilder instance
     *
     * @param DocumentType $type Document type (default: FE - Electronic invoice)
     */
    public function __construct(DocumentType $type = DocumentType::FatturaElettronica)
    {
        // Initialize with defaults
        $this->document = new DocumentData($type, 0.00);
        $this->document->totalWithoutTax = 0.00;
        $this->document->vatAmount = 0.00;

    }

    /**
     * Static factory method
     *
     * @param DocumentType $type Document type (default: FE)
     */
    public static function create(DocumentType $type = DocumentType::FatturaElettronica): self
    {
        return new self($type);
    }

    // =========================================================================
    // DOCUMENT
    // =========================================================================

    /**
     * Set document totals (all values must be provided - no calculations)
     *
     * @param float $total Total amount (including VAT)
     * @param float $totalWithoutTax Total amount without VAT
     * @param float $vatAmount VAT amount
     */
    public function totals(float $total, float $totalWithoutTax, float $vatAmount): self
    {
        $this->document->total = $total;
        $this->document->totalWithoutTax = $totalWithoutTax;
        $this->document->vatAmount = $vatAmount;

        return $this;
    }

    /**
     * Set payment method
     *
     * @param string $code Payment code (e.g., 'MP05' for bank transfer)
     * @param string $name
     * @param string $description Payment description (optional)
     */
    public function payment(string $code, string $name, string $description = ''): self
    {
        $this->document->setPayment($code, $name, $description);

        return $this;
    }

    /**
     * Set template ID
     */
    public function template(int $id): self
    {
        $this->document->idTemplate = $id;

        return $this;
    }

    /**
     * Set numerator ID
     */
    public function numerator(int $id): self
    {
        $this->document->idNumerator = $id;

        return $this;
    }

    /**
     * Set whether to send email notification
     */
    public function sendEmail(bool $send = true): self
    {
        $this->document->sendEmail = $send;

        return $this;
    }

    /**
     * Set currency (default: EUR)
     */
    public function currency(string $currency): self
    {
        $this->document->currency = $currency;

        return $this;
    }

    /**
     * Set invoice number
     */
    public function number(string $number): self
    {
        $this->document->number = $number;

        return $this;
    }

    /**
     * Set invoice object/description
     */
    public function object(string $object): self
    {
        $this->document->object = $object;

        return $this;
    }

    /**
     * Set foot notes
     */
    public function footNotes(string $notes): self
    {
        $this->document->footNotes = $notes;

        return $this;
    }

    // =========================================================================
    // CUSTOMER
    // =========================================================================

    /**
     * Set customer basic info
     *
     * @param string $name Customer name (required)
     * @param string $country Country code (default: IT)
     * @param string|null $email Customer email (optional)
     */
    public function customer(string $name, string $country = 'IT', ?string $email = null): self
    {
        $this->customer = new CustomerData($name);
        $this->customer->customerCountry = $country;

        if ($email !== null) {
            $this->customer->customerEmail = $email;
        }

        return $this;
    }

    /**
     * Quick factory for simple cases with customer name upfront
     *
     * @param string $name Customer name (required)
     * @param string $country Country code (default: IT)
     * @param DocumentType $type Document type (default: FE)
     */
    public static function forCustomer(
        string $name,
        string $country = 'IT',
        DocumentType $type = DocumentType::FatturaElettronica
    ): self {
        return self::create($type)->customer($name, $country);
    }

    /**
     * Set customer address
     *
     * @param string $address Street address
     * @param string $city City
     * @param string $postcode Postal code
     * @param string|null $province Province code (optional)
     */
    public function address(
        string $address,
        string $city,
        string $postcode,
        ?string $province = null
    ): self {

        $this->ensureCustomer();

        $this->customer->customerAddress = $address;
        $this->customer->customerCity = $city;
        $this->customer->customerPostcode = $postcode;

        if ($province !== null) {
            $this->customer->customerProvince = $province;
        }

        return $this;
    }

    /**
     * Set customer fiscal code
     */
    public function fiscalCode(string $code): self
    {
        $this->ensureCustomer();
        $this->customer->setCustomerFiscalCode($code);

        return $this;
    }

    /**
     * Set customer VAT number
     */
    public function vatNumber(string $number): self
    {
        $this->ensureCustomer();
        $this->customer->setCustomerVatCode($number);

        return $this;
    }

    /**
     * Set customer PEC (certified email)
     */
    public function pec(string $pec): self
    {
        $this->ensureCustomer();
        $this->customer->feCustomerPec = $pec;

        return $this;
    }

    /**
     * Set customer SDI code (destination code)
     */
    public function sdi(string $sdi): self
    {
        $this->ensureCustomer();
        $this->customer->feDestinationCode = $sdi;

        return $this;
    }

    /**
     * Set customer phone number
     */
    public function phone(string $phone): self
    {
        $this->ensureCustomer();
        $this->customer->customerCellPhone = $phone;

        return $this;
    }

    /**
     * Ensure customer is initialized (for methods that modify customer)
     * @throws LogicException if customer() was not called first
     */

    private function ensureCustomer(): void
    {
        if ($this->customer === null) {
            throw new LogicException('Customer must be set first. Use ->customer() method before setting other customer fields.');
        }
    }

    // =========================================================================
    // ROWS
    // =========================================================================

    /**
     * Add invoice row
     *
     * @param string $description Item description
     * @param float $quantity Quantity
     * @param float $price Unit price (can be zero or negative)
     * @param int $vatRate VAT rate percentage (0-100)
     * @param string|null $vatNature VAT nature code (required if vatRate = 0)
     */
    public function row(
        string $description,
        float $quantity,
        float $price,
        int $vatRate,
        ?string $vatNature = null
    ): self {
        $row = new RowData($description, $quantity, $price, $vatRate);

        if ($vatNature !== null) {
            $row->feVatNature = $vatNature;
        }

        $this->rows[] = $row;

        return $this;
    }

    /**
     * Add invoice row with discount
     *
     * @param string $description Item description
     * @param float $quantity Quantity
     * @param float $price Unit price
     * @param int $vatRate VAT rate percentage
     * @param float $discount Discount percentage (0-100)
     * @param string|null $vatNature VAT nature code (optional)
     */
    public function rowWithDiscount(
        string $description,
        float $quantity,
        float $price,
        int $vatRate,
        float $discount,
        ?string $vatNature = null
    ): self {
        $row = new RowData($description, $quantity, $price, $vatRate);
        $row->discounts = $discount;

        if ($vatNature !== null) {
            $row->feVatNature = $vatNature;
        }

        $this->rows[] = $row;

        return $this;
    }

    /**
     * Add a pre-built RowData object
     */
    public function addRow(RowData $row): self
    {
        $this->rows[] = $row;

        return $this;
    }

    /**
     * Clear all rows
     */
    public function clearRows(): self
    {
        $this->rows = [];

        return $this;
    }

    // =========================================================================
    // DELIVERY
    // =========================================================================

    /**
     * Set delivery address
     *
     * @param string $name Delivery name/company
     * @param string $address Delivery street address
     * @param string $city Delivery city
     * @param string $postcode Delivery postal code
     * @param string|null $province Delivery province (optional)
     * @param string|null $country Delivery country (optional)
     */
    public function delivery(
        string $name,
        string $address,
        string $city,
        string $postcode,
        ?string $province = null,
        ?string $country = null
    ): self {
        $this->delivery = new DeliveryData();
        $this->delivery->deliveryName = $name;
        $this->delivery->deliveryAddress = $address;
        $this->delivery->deliveryCity = $city;
        $this->delivery->deliveryPostcode = $postcode;

        if ($province !== null) {
            $this->delivery->deliveryProvince = $province;
        }

        if ($country !== null) {
            $this->delivery->deliveryCountry = $country;
        }

        return $this;
    }

    /**
     * Set delivery using pre-built DeliveryData object
     */
    public function setDelivery(DeliveryData $delivery): self
    {
        $this->delivery = $delivery;

        return $this;
    }

    // =========================================================================
    // PAYMENTS
    // =========================================================================

    /**
     * Add payment installment
     *
     * @param string $date Payment date (format: YYYY-MM-DD)
     * @param float $amount Payment amount
     * @param bool $paid Whether payment is already paid
     */
    public function addPaymentInstallment(
        string $date,
        float $amount,
        bool $paid = false
    ): self {
        $this->payments[] = new PaymentData($date, $amount, $paid);

        return $this;
    }

    /**
     * Add pre-built PaymentData object
     */
    public function addPayment(PaymentData $payment): self
    {
        $this->payments[] = $payment;

        return $this;
    }

    /**
     * Clear all payment installments
     */
    public function clearPayments(): self
    {
        $this->payments = [];

        return $this;
    }

    // =========================================================================
    // BUILD
    // =========================================================================

    /**
     * Build and return InvoiceData object
     *
     * uses InvoiceData's fluent interface to add optional fields.
     * @return InvoiceData complete invoice ready to send
     * @throws InvalidArgumentException if required fields are missing
     */
    public function build(): InvoiceData
    {

        if ($this->customer === null || empty($this->customer->customerName)) {
            throw new InvalidArgumentException('Customer name is required. Use ->customer() method before calling build().');
        }

        if (empty($this->rows)) {
            throw new InvalidArgumentException('At least one row is required. Use ->row() method to add rows before calling build().');
        }

        $invoice = new InvoiceData(
            $this->document,
            $this->customer,
            $this->rows
        );

        if ($this->delivery !== null) {
            $invoice->withDelivery($this->delivery);
        }

        if (!empty($this->payments)) {
            $invoice->withPayments($this->payments);
        }

        return $invoice;
    }

    /**
     * Reset builder to initial state (for reuse in loops)
     */
    public function reset(): self
    {
        $type = $this->document->documentType;

        $this->document = new DocumentData($type, 0.00);
        $this->document->totalWithoutTax = 0.00;
        $this->document->vatAmount = 0.00;

        $this->customer = null;

        $this->rows = [];
        $this->delivery = null;
        $this->payments = [];

        return $this;
    }

    // =========================================================================
    // INSPECTION (for advanced use)
    // =========================================================================

    /**
     * Get current document data (for inspection/modification)
     */
    public function getDocument(): DocumentData
    {
        return $this->document;
    }

    /**
     * Get current customer data (for inspection/modification)
     */
    public function getCustomer(): CustomerData | null
    {
        return $this->customer;
    }

    /**
     * Get current rows (for inspection/modification)
     *
     * @return RowData[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Get current delivery data (for inspection/modification)
     */
    public function getDelivery(): ?DeliveryData
    {
        return $this->delivery;
    }

    /**
     * Get current payments (for inspection/modification)
     *
     * @return PaymentData[]
     */
    public function getPayments(): array
    {
        return $this->payments;
    }

    /**
     * Get row count
     */
    public function getRowCount(): int
    {
        return \count($this->rows);
    }
}
