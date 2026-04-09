<?php

/**
 * 02-invoice-exempt-vat.php
 *
 * Fattura elettronica con IVA esente (aliquota 0% + natura N4).
 * Caso tipico: prestazioni sanitarie, scolastiche, assicurative.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Davidakis\Fattura24SDK\Fattura24Client;
use Davidakis\Fattura24SDK\Data\DocumentData;
use Davidakiss\Fattura24SDK\Data\DocumentType;
use Davidakis\Fattura24SDK\Data\CustomerData;
use Davidakis\Fattura24SDK\Data\RowData;
use Davidakis\Fattura24SDK\Data\InvoiceData;

$config = require __DIR__ . '/config.php';

$client = new Fattura24Client(['apiKey' => $config['apiKey']]);

// Documento — totale = imponibile, IVA = 0
$document = new DocumentData(
    documentType: DocumentType::FatturaElettronica,
    total: 150.00,
);
$document->totalWithoutTax = 150.00;
$document->vatAmount       = 0.00;
$document->footNotes       = 'Operazione esente IVA ai sensi dell\'art. 10 DPR 633/72';

// Cliente con codice destinatario e PEC
$customer = new CustomerData('Studio Medico Bianchi');
$customer->customerCountry = 'IT';
$customer->setCustomerVatCode('12345678901');
$customer->feDestinationCode = '0000000';
$customer->setFeCustomerPec('studiobianchi@pec.it');

// Riga con natura IVA esente
$row = new RowData('Visita specialistica', 1, 150.00, 0);
$row->feVatNature = 'N4'; // Esente art. 10

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