<?php

/**
 * 05-download-pdf.php
 *
 * Tre modalità di gestione PDF dopo la creazione di una fattura:
 *   A) Salvataggio su disco
 *   B) Stream diretto al browser
 *   C) Link temporaneo (quando gli header sono già stati inviati)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\DocumentType;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\RowData;
use SimplyIT\Fattura24SDK\Data\InvoiceData;

$config = require __DIR__ . '/config.php';

// ─── Setup comune ────────────────────────────────────────────────────────────

$document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
$document->totalWithoutTax = 100.00;
$document->vatAmount       = 22.00;

$customer = new CustomerData('Mario Rossi');
$customer->customerCountry = 'IT';
$customer->setCustomerFiscalCode('RSSMRA80A01H501U');

$row     = new RowData('Servizio', 1, 100.00, 22);
$invoice = new InvoiceData($document, $customer, [$row]);

// ─── A) Salva su disco ───────────────────────────────────────────────────────

$clientA = new Fattura24Client([
    'apiKey' => $config['apiKey'],
    'pdfDir' => '/var/www/fatture',
]);

try {
    $response = $clientA->saveDocument($invoice);
    $filepath = $clientA->downloadPdf($response->docId);

    echo "PDF salvato in: {$filepath}\n";
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

// ─── B) Stream al browser ────────────────────────────────────────────────────
/*
$clientB  = new Fattura24Client(['apiKey' => 'YOUR_API_KEY']);
$response = $clientB->saveDocument($invoice);
$clientB->downloadPdf($response->docId); // invia direttamente al browser
exit();
*/

// ─── C) Link temporaneo (header già inviati, es. in WordPress) ───────────────
/*
$clientC = new Fattura24Client(['apiKey' => 'YOUR_API_KEY']);

$clientC->getPdfManager()->setUrlGenerator(
    fn($id) => home_url("/pdf/{$id}")       // WordPress
    // fn($id) => route('pdf.download', $id) // Laravel
);

$response = $clientC->saveDocument($invoice);
$result   = $clientC->downloadPdf($response->docId);

echo "Link temporaneo: {$result['url']}\n";
*/