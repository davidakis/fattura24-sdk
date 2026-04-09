<?php

namespace Davidakis\Fattura24SDK\Data;

/**
 * DeliveryData
 *
 * Optional delivery address section.
 * All fields are optional — include only what you need.
 */
class DeliveryData
{
    public ?string $deliveryName     = null;
    public ?string $deliveryAddress  = null;
    public ?string $deliveryPostcode = null;
    public ?string $deliveryCity     = null;
    public ?string $deliveryProvince = null;

    /** @var string|null ISO 3166-1 alpha-2 (e.g. 'IT') */
    public ?string $deliveryCountry  = null;
}
