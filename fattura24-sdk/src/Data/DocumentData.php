<?php

namespace SimplyIT\Fattura24SDK\Data;

/**
 * DocumentData
 *
 * Typed value object for the Document section of a Fattura24 XML.
 * Required fields are constructor parameters.
 * Optional fields are public properties with defaults.
 *
 * Note: CustomerData and DeliveryData share the same six address fields
 * (Name, Address, Postcode, City, Province, Country) but with different
 * prefixes (Customer* vs Delivery*). A shared base class was considered
 * but rejected: with prefixed field names inheritance cannot remove the
 * duplication, and a purely nominal base class adds complexity without
 * benefit. The redundancy is intentional and kept under control.
 */
class DocumentData
{
    // -------------------------------------------------------------------------
    // Required fields
    // -------------------------------------------------------------------------

    /** @var DocumentType Document type — use DocumentType enum cases */
    public DocumentType $documentType;

    /** @var float Total amount including VAT */
    public float $total;

    /** @var float Total amount excluding VAT */
    public float $totalWithoutTax;

    /** @var float VAT amount in currency (e.g. 220.00) */
    public float $vatAmount;

    /** @var bool Whether to send the document by email */
    public bool $sendEmail;

    /** @var string Fattura24 payment code (e.g. 'MP05' = bonifico) */
    public string $fePaymentCode;

    /** @var string Payment method label shown on document */
    public string $paymentMethodName;

    /** @var string Payment method description shown on document */
    public string $paymentMethodDescription;

    // -------------------------------------------------------------------------
    // Optional fields
    // -------------------------------------------------------------------------

    /** @var string|null ISO 4217 currency code (default EUR) */
    public ?string $currency = null;

    /** @var string|null Electronic invoice document type (e.g. 'TD04' for credit note) */
    public ?string $feDocType = null;

    /**
     * @var string|null JSON params for FE credit notes.
     * Example: {"2.1.6":[{"2.1.6.2":"1423-2023-FE","2.1.6.3":"2023-02-05"}]}
     */
    public ?string $feDocParamiter = null;

    /** @var string|null Set to 'V' to apply virtual stamp */
    public ?string $feVirtualStamp = null;

    /** @var string|null Footer notes printed on the document */
    public ?string $footNotes = null;

    /** @var string|null External order ID for cross-reference */
    public ?string $f24OrderId = null;

    /** @var int|null Fattura24 template ID */
    public ?int $idTemplate = null;

    /** @var int|null Fattura24 numerator (sezionale) ID */
    public ?int $idNumerator = null;

    /** @var string|null Document subject / object */
    public ?string $object = null;

    /** @var string|null Force a specific document number */
    public ?string $number = null;

    public function __construct(
        DocumentType $documentType,
        float        $total,
        float        $totalWithoutTax,
        float        $vatAmount,
        bool         $sendEmail,
        string       $fePaymentCode,
        string       $paymentMethodName,
        string       $paymentMethodDescription
    ) {
        $this->documentType             = $documentType;
        $this->total                    = $total;
        $this->totalWithoutTax          = $totalWithoutTax;
        $this->vatAmount                = $vatAmount;
        $this->sendEmail                = $sendEmail;
        $this->fePaymentCode            = $fePaymentCode;
        $this->paymentMethodName        = $paymentMethodName;
        $this->paymentMethodDescription = $paymentMethodDescription;
    }
}
