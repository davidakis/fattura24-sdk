<?php

declare(strict_types=1);

namespace Davidakis\Fattura24SDK\Log;

/**
 * FileLogger
 *
 * Logger pronto all'uso che scrive su file di testo con rotazione giornaliera.
 *
 * Caratteristiche:
 *  - Formato: [YYYY-MM-DD HH:MM:SS] [LEVEL] message {context JSON}
 *  - Rotazione automatica giornaliera: un file per giorno (sdk-2025-03-20.log)
 *  - Redazione automatica dell'API key ovunque appaia nel messaggio o nel context
 *  - Scrittura atomica con LOCK_EX per ambienti multi-process
 *  - Creazione automatica della directory se non esiste
 *
 * Uso:
 *   $client = new Fattura24Client([
 *       'apiKey' => '...',
 *       'logger' => new FileLogger('/var/log/timetoinvoice'),
 *   ]);
 *
 * Per loggare solo warning ed errori (produzione):
 *   new FileLogger('/var/log/timetoinvoice', LogLevel::WARNING)
 */
final class FileLogger implements LoggerInterface
{
    private readonly string $directory;
    private readonly int    $minLevel;

    /**
     * @param string $directory Percorso assoluto della cartella dove scrivere i log.
     *                          Viene creata automaticamente se non esiste.
     * @param int $minLevel Livello minimo da loggare. Default: LogLevel::DEBUG (tutto).
     *                      Usa LogLevel::INFO / WARNING / ERROR per filtrare.
     */
    public function __construct(
        string $directory,
        int $minLevel = LogLevel::DEBUG,
    ) {
        $this->directory = \rtrim($directory, '/\\');
        $this->minLevel  = $minLevel;

        if (!\is_dir($this->directory)) {
            \mkdir($this->directory, 0o755, recursive: true);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write(LogLevel::DEBUG, 'DEBUG', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write(LogLevel::INFO, 'INFO', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write(LogLevel::WARNING, 'WARNING', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write(LogLevel::ERROR, 'ERROR', $message, $context);
    }

    // ── Pulizia log ───────────────────────────────────────────────────────────

    /**
     * Elimina tutti i file di log nella directory gestita da questo logger.
     * Tocca solo i file che corrispondono al pattern `sdk-YYYY-MM-DD.log`.
     */
    public function clearAll(): void
    {
        foreach ($this->logFiles() as $file) {
            @\unlink($file);
        }
    }

    /**
     * Elimina i file di log più vecchi di $days giorni.
     * Il giorno corrente non viene mai eliminato, anche con $days = 0.
     *
     * @param int $days Numero di giorni di retention (es. 30 → elimina i file oltre 30 giorni fa)
     */
    public function clearOlderThan(int $days): void
    {
        $cutoff = \strtotime("-{$days} days 00:00:00");

        foreach ($this->logFiles() as $file) {
            $date = $this->dateFromFilename(\basename($file));
            if ($date !== null && $date < $cutoff) {
                @\unlink($file);
            }
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Restituisce i percorsi assoluti di tutti i file `sdk-*.log` nella directory.
     *
     * @return string[]
     */
    private function logFiles(): array
    {
        return \glob("{$this->directory}/sdk-*.log") ?: [];
    }

    /**
     * Estrae il timestamp Unix dalla data nel nome del file (`sdk-YYYY-MM-DD.log`).
     * Restituisce null se il nome non corrisponde al pattern atteso.
     */
    private function dateFromFilename(string $filename): ?int
    {
        if (\preg_match('/^sdk-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $m)) {
            $ts = \strtotime($m[1] . ' 00:00:00');

            return $ts !== false ? $ts : null;
        }

        return null;
    }

    // -------------------------------------------------------------------------

    private function write(int $level, string $label, string $message, array $context): void
    {
        if ($level < $this->minLevel) {
            return;
        }

        $timestamp = \date('Y-m-d H:i:s');
        $date      = \date('Y-m-d');
        $path      = "{$this->directory}/sdk-{$date}.log";

        $message = $this->redact($message);
        $context = $this->redactArray($context);

        $line = "[{$timestamp}] [{$label}] {$message}";

        if (!empty($context)) {
            $line .= ' ' . \json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= PHP_EOL;

        \file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Oscura l'API key (e suoi frammenti) ovunque appaia nella stringa.
     * Pattern: sequenze alfanumeriche di 20+ caratteri tipiche delle API key Fattura24.
     */
    private function redact(string $value): string
    {
        // Oscura valori dopo apiKey= in query string (form-urlencoded)
        $value = \preg_replace('/(?<=apiKey=)[^&\s]+/', '[REDACTED]', $value) ?? $value;

        return $value;
    }

    /**
     * Redazione ricorsiva su array di context.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function redactArray(array $data): array
    {
        $sensitiveKeys = ['apiKey', 'api_key', 'key', 'password', 'token', 'secret'];

        foreach ($data as $k => $v) {
            if (\in_array(\strtolower((string) $k), \array_map('strtolower', $sensitiveKeys), true)) {
                $data[$k] = '[REDACTED]';
            } elseif (\is_array($v)) {
                $data[$k] = $this->redactArray($v);
            } elseif (\is_string($v)) {
                $data[$k] = $this->redact($v);
            }
        }

        return $data;
    }
}
