<?php

namespace SimplyIT\Fattura24SDK\Api;

/**
 * Routes
 *
 * All Fattura24 API endpoint URLs as constants.
 */
class Routes
{
    const ENDPOINT = 'https://www.app.fattura24.com/api/v0.3/';

    const TEST_KEY      = self::ENDPOINT . 'TestKey';
    const SAVE_CUSTOMER = self::ENDPOINT . 'SaveCustomer';
    const SAVE_DOCUMENT = self::ENDPOINT . 'SaveDocument';
    const GET_FILE      = self::ENDPOINT . 'GetFile';
    const GET_TEMPLATE  = self::ENDPOINT . 'GetTemplate';
    const GET_NUMERATOR = self::ENDPOINT . 'GetNumerator';
    const GET_PDC       = self::ENDPOINT . 'GetPdc';
    const GET_CALL_LOG  = self::ENDPOINT . 'GetCallLog';
}
