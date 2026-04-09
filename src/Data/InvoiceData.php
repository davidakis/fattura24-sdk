<?php

namespace Davidakisssss\Fattura24SDK\Data;

use Davidakisssssss\Fattura24SDK\Exceptions\ValidationException;

/**
 * InvoiceData
 *
 * Top-level container passed to Fattura24Client::saveDocument().
 * Aggregates DocumentData, CustomerData, RowData[], and optional
 * DeliveryData / PaymentData[].
 */
class InvoiceData
{
    /** @var DocumentData */
    public DocumentData $document;

    /** @var CustomerData */
    public CustomerData $customer;

    /** @var RowData[] At least one row is required */
    public array $rows;

    /** @var DeliveryData|null Optional shipping address */
    public ?DeliveryData $delivery = null;

    /** @var PaymentData[] Optional payment instalments */
    public array $payments = [];

    /**
     * @param DocumentData $document
     * @param CustomerData $customer
     * @param RowData[] $rows
     *
     * @throws ValidationException
     */
    public function __construct(
        DocumentData $document,
        CustomerData $customer,
        array $rows
    ) {
        if (empty($rows)) {
            throw new ValidationException('InvoiceData requires at least one RowData.');
        }

        foreach ($rows as $i => $row) {
            if (!$row instanceof RowData) {
                throw new ValidationException(
                    "rows[{$i}] must be an instance of RowData."
                );
            }
        }

        $this->document = $document;
        $this->customer = $customer;
        $this->rows     = $rows;
    }

    /**
     * Fluent setter for delivery address.
     */
    public function withDelivery(DeliveryData $delivery): self
    {
        $this->delivery = $delivery;

        return $this;
    }

    /**
     * Fluent setter for payment instalments.
     *
     * @param PaymentData[] $payments
     * @throws ValidationException
     */
    public function withPayments(array $payments): self
    {
        foreach ($payments as $i => $payment) {
            if (!$payment instanceof PaymentData) {
                throw new ValidationException(
                    "payments[{$i}] must be an instance of PaymentData."
                );
            }
        }

        $this->payments = $payments;

        return $this;
    }
}
