<?php

namespace Davidakis\Fattura24SDK\Validation;

use InvalidArgumentException;

/**
 * ItalianTaxValidator
 *
 * Validates Italian tax codes (P.IVA, Codice Fiscale, SDI).
 * Validation is ONLY applied when customer country is IT.
 */
class ItalianTaxValidator
{
    /**
     * Validates Italian VAT number (Partita IVA).
     * Must be 11 digits. Check digit validation intentionally skipped for safety.
     *
     * @param string $vat VAT number without IT prefix
     * @param string $country Customer country code
     * @throws InvalidArgumentException if invalid
     */
    public static function validateVat(string $vat, string $country): void
    {
        // Only validate for Italian customers
        if (\strtoupper($country) !== 'IT') {
            return;
        }

        $vat = \trim($vat);

        if ($vat === '') {
            return; // Empty is allowed
        }

        // Must be exactly 11 digits
        if (!\preg_match('/^\d{11}$/', $vat)) {
            throw new InvalidArgumentException(
                "P.IVA italiana deve essere 11 cifre numeriche. Ricevuto: {$vat}"
            );
        }
    }

    /**
     * Validates Italian fiscal code (Codice Fiscale).
     * Must be 16 alphanumeric characters. Complex checksum intentionally skipped.
     *
     * @param string $fiscalCode Fiscal code
     * @param string $country Customer country code
     * @throws InvalidArgumentException if invalid
     */
    public static function validateFiscalCode(string $fiscalCode, string $country): void
    {
        // Only validate for Italian customers
        if (\strtoupper($country) !== 'IT') {
            return;
        }

        $fiscalCode = \strtoupper(\trim($fiscalCode));

        if ($fiscalCode === '') {
            return; // Empty is allowed
        }

        // Must be exactly 16 alphanumeric
        if (!\preg_match('/^[A-Z0-9]{16}$/', $fiscalCode)) {
            throw new InvalidArgumentException(
                "Codice Fiscale deve essere 16 caratteri alfanumerici. Ricevuto: {$fiscalCode}"
            );
        }
    }

    /**
     * Validates SDI code (Codice Destinatario).
     * Must be 6 or 7 alphanumeric characters.
     *
     * @param string $sdi SDI code
     * @throws InvalidArgumentException if invalid
     */
    public static function validateSdi(string $sdi): void
    {
        $sdi = \strtoupper(\trim($sdi));

        if ($sdi === '') {
            return; // Empty is allowed
        }

        // Must be 6 or 7 alphanumeric characters
        if (!\preg_match('/^[A-Z0-9]{6,7}$/', $sdi)) {
            throw new InvalidArgumentException(
                "Codice SDI deve essere 6 o 7 caratteri alfanumerici. Ricevuto: {$sdi}"
            );
        }
    }

    /**
     * Validates PEC email address.
     * Basic email format check.
     *
     * @param string $pec PEC email
     * @throws InvalidArgumentException if invalid
     */
    public static function validatePec(string $pec): void
    {
        $pec = \trim($pec);

        if ($pec === '') {
            return; // Empty is allowed
        }

        // Basic email validation
        if (!\filter_var($pec, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                "PEC deve essere un indirizzo email valido. Ricevuto: {$pec}"
            );
        }
    }

    /**
     * Sanitizes a string for Italian tax codes.
     * Trims whitespace and converts to uppercase.
     */
    public static function sanitize(string $value): string
    {
        return \strtoupper(\trim($value));
    }

    /**
     * Returns default SDI code based on customer country.
     * Used when SDI field is empty or not provided.
     *
     * @param string $country Customer country code
     * @return string '0000000' for IT, 'XXXXXXX' for non-IT countries
     */
    public static function getDefaultSdi(string $country): string
    {
        return \strtoupper($country) === 'IT' ? '0000000' : 'XXXXXXX';
    }
}
