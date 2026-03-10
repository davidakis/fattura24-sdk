<?php

/**
 * 03-invoice-multiple-vat-rates.php
 *
 * Fattura con righe a diverse aliquote IVA (4%, 10%, 22%).
 * I totali vanno calcolati e passati esplicitamente — l'SDK non calcola.
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

// Righe con aliquote diverse
// Prodotto alimentare base: 50.00 + 4% IVA = 52.00
$row1 = new RowData('Prodotto alimentare', 2, 25.00, 4);

// Prodotto alimentare trasformato: 100.00 + 10% IVA = 110.00
$row2 = new RowData('Prodotto alimentare trasformato', 1, 100.00, 10);

// Servizio: 200.00 + 22% IVA = 244.00
$row3 = new RowData('Servizio di consegna', 1, 200.00, 22);

// Totali: imponibile 350.00, IVA (2+10+44) = 56.00, totale 406.00
$document = new DocumentData(
    documentType: DocumentType::FatturaElettronica,
    total: 406.00,
);
$document->totalWithoutTax = 350.00;
$document->vatAmount       = 56.00;

$customer = new CustomerData('Supermercato Verdi SRL');
$customer->customerCountry = 'IT';
$customer->setCustomerVatCode('98765432101');
$customer->feDestinationCode = 'ABC1234';

$invoice  = new InvoiceData($document, $customer, [$row1, $row2, $row3]);

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