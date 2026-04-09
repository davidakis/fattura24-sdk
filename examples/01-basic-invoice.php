<?php

/**
 * 01-basic-invoice.php
 *
 * Fattura elettronica semplice con un singolo articolo.
 * Caso d'uso più comune: consulenza o servizio a cliente italiano.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Davidakis\Fattura24SDK\Fattura24Client;
use Davidakis\Fattura24SDK\Data\DocumentData;
use Davidakis\Fattura24SDK\Data\DocumentType;
use Davidakis\Fattura24SDK\Data\CustomerData;
use Davidakis\Fattura24SDK\Data\RowData;
use Davidakis\Fattura24SDK\Data\InvoiceData;

$config = require __DIR__ . '/config.php';


$client = new Fattura24Client([
    'apiKey' => $config['apiKey'],
    'source' => 'MyApp',
]);

// Documento
$document = new DocumentData(
    documentType: DocumentType::FatturaElettronica,
    total: 122.00,
);
$document->totalWithoutTax = 100.00;
$document->vatAmount       = 22.00;
$document->setPayment('MP05', 'Bonifico bancario', 'IBAN: IT60X0542811101000000123456');

// Cliente
$customer = new CustomerData('Mario Rossi');
$customer->customerCountry = 'IT';
$customer->setCustomerFiscalCode('RSSMRA80A01H501U');
$customer->customerEmail = 'mario.rossi@example.com';

// Riga
$row = new RowData('Consulenza tecnica', 1, 100.00, 22);

// Fattura
$invoice  = new InvoiceData($document, $customer, [$row]);

try {
    $response = $client->saveDocument($invoice);

    echo "SUCCESS!\n\n";
    echo "Invoice created:\n";
    echo "- Invoice number: {$response->docNumber}\n";
    echo "- Document ID: {$response->docId}\n";
} catch (Exception $e) {
    echo "Error: {$e->getMessage()}\n";
}

echo PHP_EOL;
echo "IMPORTANT:\n";
echo "This is a REAL invoice in your Fattura24 account.\n";
echo "Delete it from your dashboard if it was a test.\n";
echo "\n";
echo "TIP: you can also use InvoiceBuilder for more concise code:\n";
echo "See main Readme.md for some examples.\n";