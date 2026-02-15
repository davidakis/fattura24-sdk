<?php

namespace SimplyIT\Fattura24SDK\Data;

/**
 * PaymentData
 *
 * Typed value object for a single payment instalment.
 */
class PaymentData
{
    /** @var string Date in Y-m-d format (e.g. '2025-03-31') */
    public string $date;

    /** @var float Amount due on this date */
    public float $amount;

    /** @var bool Whether the instalment has already been paid */
    public bool $paid;

    public function __construct(string $date, float $amount, bool $paid = false)
    {
        $this->date   = $date;
        $this->amount = $amount;
        $this->paid   = $paid;
    }
}
