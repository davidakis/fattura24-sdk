<?php

/**
 * 08-error-handling.php
 *
 * Gestione completa degli errori possibili nell'SDK:
 *   - MissingApiKeyException  → configurazione mancante
 *   - ValidationException     → dati fiscali non validi
 *   - Fattura24Exception      → errore restituito dall'API
 *   - RuntimeException        → errore di rete / cURL
 *
 * Mostra anche come usare $idRequest per l'idempotenza.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Builder\InvoiceBuilder;
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;
use SimplyIT\Fattura24SDK\Exceptions\MissingApiKeyException;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;

$config = require __DIR__ . '/config.php';

// ─── 1. MissingApiKeyException ───────────────────────────────────────────────

try {
    $client = new Fattura24Client([]); // apiKey mancante
} catch (MissingApiKeyException $e) {
    echo "Configurazione errata: {$e->getMessage()}\n";
}

// ─── 2. ValidationException (CF non valido) ──────────────────────────────────

try {
    $client  = new Fattura24Client(['apiKey' => $config['apiKey']]);

    $invoice = InvoiceBuilder::create()
        ->customer('Mario Rossi', 'IT')
        ->fiscalCode('CODICE_NON_VALIDO') // lancia ValidationException
        ->totals(122.00, 100.00, 22.00)
        ->row('Consulenza', 1, 100.00, 22)
        ->build();

} catch (ValidationException $e) {
    echo "Dato fiscale non valido: {$e->getMessage()}\n";
}

// ─── 3. Fattura24Exception (errore API) ──────────────────────────────────────

try {
    $client = new Fattura24Client(['apiKey' => 'CHIAVE_ERRATA']);

    $invoice = InvoiceBuilder::create()
        ->customer('Mario Rossi', 'IT')
        ->totals(122.00, 100.00, 22.00)
        ->row('Consulenza', 1, 100.00, 22)
        ->build();

    $response = $client->saveDocument($invoice);

} catch (Fattura24Exception $e) {
    echo "Errore API Fattura24: {$e->getMessage()}\n";
}

// ─── 4. Invio con idempotenza ($idRequest) ───────────────────────────────────
//
// Passare un $idRequest univoco permette a Fattura24 di deduplicare la
// richiesta in caso di retry (es. timeout di rete). Se la stessa richiesta
// viene inviata due volte con lo stesso ID, viene creata una sola fattura.

try {
    $client = new Fattura24Client(['apiKey' => $config['apiKey']]);

    $invoice = InvoiceBuilder::create()
        ->customer('Mario Rossi', 'IT', 'mario@example.com')
        ->fiscalCode('RSSMRA80A01H501U')
        ->totals(122.00, 100.00, 22.00)
        ->row('Consulenza tecnica', 1, 100.00, 22)
        ->build();

    // Genera un ID univoco per questa transazione (es. da ordine e-commerce)
    $idRequest = 'order-' . uniqid();
    $response  = $client->saveDocument($invoice, $idRequest);

    echo "Fattura #{$response->docNumber} creata (idRequest: {$idRequest})\n";

} catch (ValidationException $e) {
    echo "Validazione: {$e->getMessage()}\n";
} catch (Fattura24Exception $e) {
    echo "API: {$e->getMessage()}\n";
} catch (\RuntimeException $e) {
    // Errori di rete, cURL, timeout
    echo "Errore di rete: {$e->getMessage()}\n";
}