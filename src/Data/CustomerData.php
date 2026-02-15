<?php

namespace SimplyIT\Fattura24SDK\Data;

use SimplyIT\Fattura24SDK\Exceptions\ValidationException;

/**
 * CustomerData
 *
 * Typed value object for the Customer section.
 * CustomerName is the only truly required field.
 * FE fields (PEC / destination code) are optional but validated together.
 *
 * Note: CustomerData and DeliveryData share the same six address fields
 * (Name, Address, Postcode, City, Province, Country) but with different
 * prefixes (Customer* vs Delivery*). A shared base class was considered
 * but rejected: with prefixed field names inheritance cannot remove the
 * duplication, and a purely nominal base class adds complexity without
 * benefit. The redundancy is intentional and kept under control.
 */
class CustomerData
{
    // -------------------------------------------------------------------------
    // Required
    // -------------------------------------------------------------------------

    /** @var string Business name or full name */
    public string $customerName;

    // -------------------------------------------------------------------------
    // Address fields (all optional but recommended)
    // -------------------------------------------------------------------------

    public ?string $customerAddress  = null;
    public ?string $customerPostcode = null;
    public ?string $customerCity     = null;
    public ?string $customerProvince = null;

    /** @var string ISO 3166-1 alpha-2 country code (e.g. 'IT') */
    public ?string $customerCountry  = null;

    // -------------------------------------------------------------------------
    // Contact / fiscal fields
    // -------------------------------------------------------------------------

    public ?string $customerEmail      = null;
    public ?string $customerCellPhone  = null;
    public ?string $customerFiscalCode = null;
    public ?string $customerVatCode    = null;

    // -------------------------------------------------------------------------
    // Electronic invoice fields
    // -------------------------------------------------------------------------

    /** @var string|null PEC address for electronic invoice delivery */
    public ?string $feCustomerPec     = null;

    /** @var string|null SDI destination code (7 chars) */
    public ?string $feDestinationCode = null;

    /**
     * @throws ValidationException
     */
    public function __construct(string $customerName)
    {
        if (trim($customerName) === '') {
            throw new ValidationException('CustomerName cannot be empty.');
        }

        $this->customerName = $customerName;
    }

    /**
     * Quick check: has at least one FE delivery channel set.
     */
    public function hasFeDelivery(): bool
    {
        return !empty($this->feCustomerPec) || !empty($this->feDestinationCode);
    }
}
