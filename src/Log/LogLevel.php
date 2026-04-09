<?php

declare(strict_types=1);

namespace Davidakis\Fattura24SDK\Log;

/**
 * LogLevel
 *
 * Costanti numeriche per il filtraggio per livello nel FileLogger.
 * Ordine crescente di severità: DEBUG < INFO < WARNING < ERROR.
 */
final class LogLevel
{
    public const DEBUG   = 0;
    public const INFO    = 1;
    public const WARNING = 2;
    public const ERROR   = 3;
}
