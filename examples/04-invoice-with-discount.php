<?php

/**
 * 04-invoice-with-discount.php
 *
 * Fattura con sconto percentuale su una o più righe.
 * I totali devono già riflettere lo sconto — l'SDK non lo applica.
 *
 * Esempio: 2 prodotti a 100.00, sconto 10% su entrambi.
 * Imponibile: 180.00 (200 - 20), IVA 22%: 39.60, Totale: 219.60
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

$document = new DocumentData(
    documentType: DocumentType::FatturaElettronica,
    total: 219.60,
);
$document->totalWithoutTax = 180.00;
$document->vatAmount       = 39.60;

$customer = new CustomerData('Luca Bianchi');
$customer->customerCountry = 'IT';
$customer->setCustomerFiscalCode('BNCLCU85M10H501Z');

// Sconto 10% applicato a entrambe le righe
$row1 = new RowData('Prodotto A', 1, 100.00, 22);
$row1->discounts = 10;

$row2 = new RowData('Prodotto B', 1, 100.00, 22);
$row2->discounts = 10;

$invoice  = new InvoiceData($document, $customer, [$row1, $row2]);

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