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
    // Constructor con named parameters + defaults sensati
    // -------------------------------------------------------------------------

    /**
     * Creates a new document.
     *
     * Only documentType and total are required.
     * All other fields have sensible defaults or are nullable.
     *
     * @param DocumentType $documentType Document type (use DocumentType enum)
     * @param float $total Total amount including VAT
     * @param float|null $totalWithoutTax Total excluding VAT (set explicitly or leave null)
     * @param float|null $vatAmount VAT amount (set explicitly or leave null)
     * @param bool $sendEmail Whether to send document by email (default: false)
     * @param string $fePaymentCode Fattura24 payment code (default: MP08 = carta)
     * @param string $paymentMethodName Payment method label (default: "Pagamento con carta")
     * @param string $paymentMethodDescription Payment description (default: empty)
     */
    public function __construct(
        public DocumentType $documentType,
        public ?float $total = null,
        public ?float $totalWithoutTax = null,
        public ?float $vatAmount = null,
        public bool $sendEmail = false,
        public string $fePaymentCode = 'MP08',
        public string $paymentMethodName = 'Pagamento con carta',
        public string $paymentMethodDescription = '',
    ) {
    }

    // -------------------------------------------------------------------------
    // Optional fields
    // -------------------------------------------------------------------------

    /** @var string|null ISO 4217 currency code (default EUR) */
    public ?string $currency = null;

    /** @var string|null Electronic invoice document type (e.g. 'TD04' for credit note) */
    public ?string $feDocType = null;

    /**
     * @var string|null JSON params for FE credit notes.
     *                  Example: {"2.1.6":[{"2.1.6.2":"1423-2023-FE","2.1.6.3":"2023-02-05"}]}
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

    private const PAYMENT_LABELS = [
        'MP01' => 'Contanti',
        'MP02' => 'Assegno',
        'MP03' => 'Assegno circolare',
        'MP04' => 'Contanti presso Tesoreria',
        'MP05' => 'Bonifico bancario',
        'MP06' => 'Vaglia cambiario',
        'MP07' => 'Bollettino bancario',
        'MP08' => 'Pagamento con carta',
        'MP09' => 'RID',
        'MP10' => 'RID utenze',
        'MP11' => 'RID veloce',
        'MP12' => 'RIBA',
        'MP13' => 'MAV',
        'MP14' => 'Quietanza erario',
        'MP15' => 'Giroconto su conti di contabilità speciale',
        'MP16' => 'Domiciliazione bancaria',
        'MP17' => 'Domiciliazione postale',
        'MP18' => 'Bollettino di c/c postale',
        'MP19' => 'SEPA Direct Debit',
        'MP20' => 'SEPA Direct Debit CORE',
        'MP21' => 'SEPA Direct Debit B2B',
        'MP22' => 'Trattenuta su somme già riscosse',
        'MP23' => 'PagoPA',
    ];
    /**
     * Sets payment information fluently.
     *
     * @param string $code Fattura24 payment code (e.g., 'MP08', 'MP05')
     * @param string $name Payment method name shown on document
     * @param string $description Optional payment description
     * @return self For method chaining
     */
    public function setPayment(string $code, ?string $name = null, string $description = ''): self
    {
        $this->fePaymentCode = $code;
        $this->paymentMethodName = $name ?? self::PAYMENT_LABELS[$code] ?? $code;
        $this->paymentMethodDescription = $description;

        return $this;
    }
}
