<?php

namespace SimplyIT\Fattura24SDK\Exceptions;

/** Thrown on HTTP errors or cURL connection failures. */
class ConnectionException extends Fattura24Exception
{
    private int $httpCode;

    public function __construct(string $message, int $httpCode = 0, \Throwable $previous = null)
    {
        $this->httpCode = $httpCode;
        parent::__construct($message, $httpCode, $previous);
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }
}
