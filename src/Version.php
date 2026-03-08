<?php

namespace SimplyIT\Fattura24SDK;

/**
 * Version
 *
 * Single source of truth for the SDK version.
 * Update CURRENT on every release, aligned with the Git tag and composer.json.
 */
class Version
{
    public const CURRENT = '2.1.0';

    /**
     * Returns the SDK identifier string, suitable for use in HTTP headers
     * or API source parameters.
     *
     * Format: "SimplyIT-Fattura24SDK-1.0.0"
     */
    public static function identifier(): string
    {
        return 'SimplyIT-Fattura24SDK-' . self::CURRENT;
    }
}
