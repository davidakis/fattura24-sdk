<?php

/**
 * Example 1: Basic invoice
 * 
 * Creates a simple invoice with one item
 */

require __DIR__ . '/../vendor/autoload.php';

use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\{
    DocumentData,
    DocumentType,
    CustomerData,
    RowData,
    InvoiceData
};

$config = require __DIR__ . 'config.php';

$client = new Fattura24Client(['apiKey' => $config['apiKey']]);
$document = new DocumentData(
    documentType: DocumentType::FatturaElettronica,
    total: 122.00);

$document->totalWithoutTax = 100.00;
$document->vatAmount = 22.00;
$document->setPayment('MP01', 'Contanti');

$customer = new CustomerData('Mario Rossi');
$customer->customerCountry = 'IT';
$customer->customerEmail = 'mario.rossi@example.com';
$customer->customerAddress = 'Via Roma, 1';
$customer->customerCity = 'Roma';
$customer->customerProvince = 'RM';
$customer->customerPostcode = '00191';
$customer->setCustomerFiscalCode('RSSMRA80A01F205C');

$rows = [];

$rows[] = new RowData(
    description: 'Consulenza tecnica',
    qty: 1,
    price: 100.00,
    vatCode: 22
);

$invoice = new InvoiceData($document, $customer, $rows);

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