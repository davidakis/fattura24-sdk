<?php

/**
 * 07-bulk-invoicing.php
 *
 * Creazione massiva di fatture con InvoiceBuilder::reset().
 * Utile per elaborare batch di ordini da un e-commerce o gestionale.
 *
 * Nota: l'SDK non gestisce rate limiting — aggiungi sleep() se necessario
 * per non superare i limiti delle API Fattura24.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Davidakis\Fattura24SDK\Fattura24Client;
use Davidakis\Fattura24SDK\Builder\InvoiceBuilder;
use Davidakis\Fattura24SDK\Exceptions\Fattura24Exception;
use Davidakis\Fattura24SDK\Exceptions\ValidationException;

$config = require __DIR__ . '/config.php';

$client  = new Fattura24Client(['apiKey' => $config['apiKey']]);
$builder = new InvoiceBuilder();

// Dati di esempio — in produzione arriveranno dal DB o dall'e-commerce
$orders = [
    [
        'customer'   => 'Mario Rossi',
        'fiscalCode' => 'RSSMRA80A01H501U',
        'email'      => 'mario@example.com',
        'total'      => 122.00,
        'taxable'    => 100.00,
        'vat'        => 22.00,
        'items'      => [
            ['desc' => 'Prodotto A', 'qty' => 1, 'price' => 100.00, 'vat' => 22],
        ],
    ],
    [
        'customer'   => 'Luca Bianchi',
        'fiscalCode' => 'BNCLCU85M10H501Z',
        'email'      => 'luca@example.com',
        'total'      => 244.00,
        'taxable'    => 200.00,
        'vat'        => 44.00,
        'items'      => [
            ['desc' => 'Prodotto B', 'qty' => 2, 'price' => 100.00, 'vat' => 22],
        ],
    ],
    [
        'customer'   => 'Anna Verdi',
        'fiscalCode' => 'VRDNNA90T41F205K',
        'email'      => 'anna@example.com',
        'total'      => 61.00,
        'taxable'    => 50.00,
        'vat'        => 11.00,
        'items'      => [
            ['desc' => 'Prodotto C', 'qty' => 5, 'price' => 10.00, 'vat' => 22],
        ],
    ],
];

$results = ['ok' => [], 'errors' => []];

foreach ($orders as $order) {
    try {
        $builder->reset()
            ->customer($order['customer'], 'IT', $order['email'])
            ->fiscalCode($order['fiscalCode'])
            ->totals($order['total'], $order['taxable'], $order['vat']);

        foreach ($order['items'] as $item) {
            $builder->row($item['desc'], $item['qty'], $item['price'], $item['vat']);
        }

        $invoice  = $builder->build();
        $response = $client->saveDocument($invoice);

        $results['ok'][] = [
            'customer' => $order['customer'],
            'docId'    => $response->docId,
            'docNum'   => $response->docNumber,
        ];

        echo "✓ {$order['customer']} → Fattura #{$response->docNumber} (ID: {$response->docId})\n";

        // Pausa opzionale per rispettare i rate limit Fattura24
        // sleep(1);

    } catch (ValidationException $e) {
        $results['errors'][] = ['customer' => $order['customer'], 'error' => $e->getMessage()];
        echo "✗ {$order['customer']} → Errore di validazione: {$e->getMessage()}\n";
    } catch (Fattura24Exception $e) {
        $results['errors'][] = ['customer' => $order['customer'], 'error' => $e->getMessage()];
        echo "✗ {$order['customer']} → Errore API: {$e->getMessage()}\n";
    }
}

echo "\nRiepilogo: " . count($results['ok']) . " ok, " . count($results['errors']) . " errori\n";