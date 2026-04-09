<?php

declare(strict_types=1);

namespace Davidakis\Fattura24SDK\Log;

/**
 * NullLogger
 *
 * Implementazione no-op di LoggerInterface.
 * È il default usato da Fattura24Client quando nessun logger viene iniettato:
 * garantisce che la SDK funzioni senza logger configurato senza alcun overhead.
 */
final class NullLogger implements LoggerInterface
{
    public function debug(string $message, array $context = []): void
    {
    }
    public function info(string $message, array $context = []): void
    {
    }
    public function warning(string $message, array $context = []): void
    {
    }
    public function error(string $message, array $context = []): void
    {
    }
}
