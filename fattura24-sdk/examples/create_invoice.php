<?php

/**
 * SimplyIT Fattura24 SDK - Usage example
 * Run with: php examples/create_invoice.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\DocumentType;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\RowData;
use SimplyIT\Fattura24SDK\Data\PaymentData;
use SimplyIT\Fattura24SDK\Data\DeliveryData;
use SimplyIT\Fattura24SDK\Data\InvoiceData;
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;
use SimplyIT\Fattura24SDK\Exceptions\ConnectionException;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;

// -----------------------------------------------------------------------
// 1. Client
// -----------------------------------------------------------------------
$client = new Fattura24Client([
    'apiKey' => 'YOUR_API_KEY_HERE',
    'source' => 'my-app',
]);

// -----------------------------------------------------------------------
// 2. Build typed objects
// -----------------------------------------------------------------------

$document = new DocumentData(
    documentType:             DocumentType::FatturaElettronica,
    total:                    1220.00,
    totalWithoutTax:          1000.00,
    vatAmount:                220.00,
    sendEmail:                false,
    fePaymentCode:            'MP05',
    paymentMethodName:        'Bonifico bancario',
    paymentMethodDescription: 'IBAN: IT00 0000 0000 0000'
);
$document->currency    = 'EUR';
$document->object      = 'Fornitura servizi web';
$document->footNotes   = 'Grazie per la fiducia.';
$document->idNumerator = 567;

$customer = new CustomerData('Acme S.r.l.');
$customer->customerAddress    = 'Via Roma, 1';
$customer->customerPostcode   = '20121';
$customer->customerCity       = 'Milano';
$customer->customerProvince   = 'MI';
$customer->customerCountry    = 'IT';
$customer->customerEmail      = 'fatture@acme.it';
$customer->customerVatCode    = '12345678910';
$customer->feCustomerPec      = 'acme@pec.it';
$customer->feDestinationCode  = 'ABCDEFG';

$row1 = new RowData('Sviluppo sito web', 1, 800.00, 22);
$row1->code           = 'SERV-001';
$row1->um             = 'pz';
$row1->vatDescription = '22%';

$row2 = new RowData('Consulenza SEO', 2, 100.00, 22);
$row2->code           = 'SERV-002';
$row2->um             = 'h';
$row2->vatDescription = '22%';

$delivery = new DeliveryData();
$delivery->deliveryName     = 'Magazzino Acme';
$delivery->deliveryAddress  = 'Via Napoli, 150';
$delivery->deliveryPostcode = '20122';
$delivery->deliveryCity     = 'Milano';
$delivery->deliveryProvince = 'MI';
$delivery->deliveryCountry  = 'IT';

$payment = new PaymentData('2025-03-31', 1220.00, false);

// -----------------------------------------------------------------------
// 3. Assemble InvoiceData
// -----------------------------------------------------------------------
try {
    $invoice = (new InvoiceData($document, $customer, [$row1, $row2]))
        ->withDelivery($delivery)
        ->withPayments([$payment]);
} catch (ValidationException $e) {
    echo "Dati non validi: " . $e->getMessage() . "\n";
    exit(1);
}

// -----------------------------------------------------------------------
// 4. Save document
// -----------------------------------------------------------------------
try {
    $result = $client->saveDocument($invoice);

    echo "Fattura creata!\n";
    echo "  docId:     {$result['docId']}\n";
    echo "  docNumber: {$result['docNumber']}\n";

} catch (ValidationException $e) {
    echo "Errore validazione: " . $e->getMessage() . "\n";
    exit(1);
} catch (ConnectionException $e) {
    echo "Errore connessione (HTTP {$e->getHttpCode()}): " . $e->getMessage() . "\n";
    exit(1);
} catch (Fattura24Exception $e) {
    echo "Errore SDK: " . $e->getMessage() . "\n";
    exit(1);
}

// -----------------------------------------------------------------------
// 5. Download PDF
// -----------------------------------------------------------------------
if (!empty($result['docId'])) {
    try {
        $file     = $client->getFile($result['docId']);
        $savePath = __DIR__ . '/' . ($file['filename'] ?: 'fattura.pdf');
        file_put_contents($savePath, $file['content']);
        echo "PDF salvato: {$savePath} ({$file['mime']})\n";
    } catch (Fattura24Exception $e) {
        echo "Download fallito: " . $e->getMessage() . "\n";
    }
}

// -----------------------------------------------------------------------
// 6. Lists
// -----------------------------------------------------------------------
$templates  = $client->getTemplates();
$numerators = $client->getNumerators();

echo "\nTemplate fatture:\n";
foreach ($templates['invoice'] as $id => $label) {
    echo "  [{$id}] {$label}\n";
}

echo "\nSezionali FE:\n";
foreach ($numerators['electronic_invoice'] as $id => $label) {
    echo "  [{$id}] {$label}\n";
}
