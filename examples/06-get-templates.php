<?php

/**
 * 06-get-templates.php
 *
 * Recupera i modelli di documento e i numeratori (sezionali) disponibili
 * sull'account Fattura24, e mostra come usarli in una fattura.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\DocumentType;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\RowData;
use SimplyIT\Fattura24SDK\Data\InvoiceData;

$config = require __DIR__ . '/config.php';

$client = new Fattura24Client(['apiKey' => $config['apiKey']]);

// ─── Modelli ─────────────────────────────────────────────────────────────────

$templates = $client->getTemplates();

echo "Modelli fattura disponibili:\n";
foreach ($templates->invoice as $id => $name) {
    echo "  [{$id}] {$name}\n";
}

echo "\nModelli ordine disponibili:\n";
foreach ($templates->order as $id => $name) {
    echo "  [{$id}] {$name}\n";
}

// ─── Numeratori ──────────────────────────────────────────────────────────────

$numerators = $client->getNumerators();
$defaultId  = $numerators->getDefaultId('invoice');

echo "\nNumeratore predefinito per le fatture: {$defaultId}\n";

// ─── Usa modello e numeratore in una fattura ─────────────────────────────────

$templateId  = array_key_first($templates->invoice); // primo disponibile
$numeratorId = $defaultId;

$document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
$document->totalWithoutTax = 100.00;
$document->vatAmount       = 22.00;
$document->idTemplate      = $templateId;
$document->idNumerator     = $numeratorId;

$customer = new CustomerData('Mario Rossi');
$customer->customerCountry = 'IT';
$customer->setCustomerFiscalCode('RSSMRA80A01H501U');

$row      = new RowData('Consulenza', 1, 100.00, 22);
$invoice  = new InvoiceData($document, $customer, [$row]);

try {
    $response = $client->saveDocument($invoice);

    echo "Fattura #{$response->docNumber} creata con template {$templateId} e numeratore {$numeratorId}\n";
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}