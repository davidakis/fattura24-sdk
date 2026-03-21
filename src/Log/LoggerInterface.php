<?php

declare(strict_types=1);

namespace SimplyIT\Fattura24SDK\Log;

/**
 * LoggerInterface
 *
 * Interfaccia minimale ispirata a PSR-3, senza dipendenze esterne.
 * Copre i soli livelli utili alla SDK: debug, info, warning, error.
 *
 * Se il progetto host usa già PSR-3 (es. Monolog), è sufficiente
 * creare un adapter che implementa questa interfaccia e delega
 * ai metodi dell'logger PSR-3 host — nessuna incompatibilità.
 */
interface LoggerInterface
{
    /**
     * Informazioni dettagliate per il debug.
     * Esempio: corpo della request, durata cURL, XML generato.
     *
     * @param string $message
     * @param array<string, mixed> $context Dati strutturati opzionali
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Evento ordinario: operazione completata con successo.
     * Esempio: documento creato, chiave verificata.
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * Situazione non bloccante ma degna di attenzione.
     * Esempio: P.IVA non valida accettata in modalità permissiva.
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Errore che ha impedito il completamento di un'operazione.
     * Esempio: risposta non valida da Fattura24, errore cURL.
     *
     * @param string $message
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void;
}
