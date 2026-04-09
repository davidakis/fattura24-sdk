<?php

namespace Davidakis\Fattura24SDK\Data;

/**
 * RowData
 *
 * Typed value object for a single product/service row.
 * Description, Qty, Price and VatCode are required.
 * FeVatNature is validated by XmlGenerator when DocumentType=FE and VatCode=0.
 */
class RowData
{
    // Required
    public string $description;
    public float  $qty;
    public ?float $price = null;
    public int    $vatCode;

    // Optional
    public ?string $code           = null;
    public ?string $um             = null;
    public ?float  $discounts      = null;
    public ?string $vatDescription = null;

    /** Required when DocumentType='FE' and VatCode=0. Valid values: N1, N2.1 ... N7 */
    public ?string $feVatNature    = null;

    /** Fattura24 chart-of-accounts entry ID */
    public ?int    $idPdc          = null;

    public function __construct(
        string $description,
        float $qty,
        float $price,
        int $vatCode
    ) {
        $this->description = $description;
        $this->qty         = $qty;
        $this->price       = $price;
        $this->vatCode     = $vatCode;
    }
}
