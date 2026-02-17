# SimplyIT Fattura24 SDK

PHP SDK tipizzato e testato per l'integrazione con le API di [Fattura24](https://www.fattura24.com/api/introduzione/).

Progettato per applicazioni personalizzate, plugin WordPress, moduli e-commerce e sistemi gestionali - senza accoppiamento a framework o piattaforme specifiche.

---

## Caratteristiche
- PHP 8.1+
- Oggetti di valore fortemente tipizzati
- Convalida dei dati prima della chiamata API
- Idempotenza opzionale
- Nessuna dipendenza da framework
- Test unitari e di integrazione
- Serializzazione XML coerente con le specifiche Fattura24


## Requisiti

| | Minimo |
|---|---|
| PHP | 8.1 |
| ext-curl | qualsiasi |
| ext-dom | qualsiasi |
| ext-simplexml | qualsiasi |

---

## Installazione

### Con Composer (consigliato)

```bash
composer require simplyit/fattura24-sdk
```

---

### Senza Composer

1. Scarica l'ultima versione dal repository GitHub
2. Estrai la cartella src/ all'interno del tuo progetto.
3. Includi un semplice autoloader PSR-4 oppure utilizza il tuo autoloader esistente.

Esempio di autoloader minimale:

```php
spl_autoload_register(function ($class) {
    $prefix = 'SimplyIT\\Fattura24SDK\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);

    if (strcnmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require($file);
    }
});
```

Assicurati che le estensioni richieste (ext-curl, ext-dom, ext-simplexml) siano abilitate.

## Guida rapida

```php
use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\DocumentType;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\RowData;
use SimplyIT\Fattura24SDK\Data\InvoiceData;

$client = new Fattura24Client(['apiKey' => 'your-api-key']);

$document = new DocumentData(
    documentType:             DocumentType::FatturaElettronica,
    total:                    1220.00,   // VAT included
    totalWithoutTax:          1000.00,   // VAT excluded
    vatAmount:                220.00,    // VAT amount in currency
    sendEmail:                false,
    fePaymentCode:            'MP05',
    paymentMethodName:        'Bonifico bancario',
    paymentMethodDescription: 'IBAN: IT00 0000 0000 0000'
);

$customer = new CustomerData('Acme S.r.l.');
$customer->customerVatCode   = '12345678910';
$customer->feCustomerPec     = 'acme@pec.it';
$customer->feDestinationCode = 'ABCDEFG';

$row = new RowData('Consulenza', 1, 1000.00, 22); // il prezzo è IVA esclusa

$result = $client->saveDocument(new InvoiceData($document, $customer, [$row]));

echo $result['docId'];     // docId in Fattura24
echo $result['docNumber']; // es.: 1/2025/FE
```

---

## Oggetti dati

L' SDK usa oggetti dal valore tipizzato per costruire il payload della chiamata. La logica di convalida viene eseguita prima di qualsiasi chiamata API.

> **Nota sui prezzi e sui totali**
> Le API di Fattura24 di aspettano che tutti gli importi siano al netto di IVA (`price`, `totalWithoutTax`) e l'importo dell'IVA è un valore separato (`vatAmount`). Il campo `total` è il totale generale IVA inclusa. L'SDK non fa calcoli — mette in serie i valori esatti che gli vengono passati. Assicurati di calcolare e passare i numeri corretti prima che vengano costruiti gli oggetti dati.

---

### `DocumentType`

| Caso | Valore |
|---|---|
| `DocumentType::Order` | `C` |
| `DocumentType::FatturaElettronica` | `FE` |
| `DocumentType::Fattura` | `I` |
| `DocumentType::FatturaForce` | `I-Force` |
| `DocumentType::Ricevuta` | `R` |

Conversioni: 
- `DocumentType::from('FE')`.
- `DocumentType::tryFrom('XYZ')` ->`null`.

---

### `DocumentData`

```php
$document = new DocumentData(
    documentType:             DocumentType::FatturaElettronica,
    total:                    1220.00,   // grand total, VAT included
    totalWithoutTax:          1000.00,   // taxable amount, VAT excluded
    vatAmount:                220.00,    // VAT in currency (not a percentage)
    sendEmail:                false,
    fePaymentCode:            'MP05',
    paymentMethodName:        'Bonifico bancario',
    paymentMethodDescription: 'IBAN: IT00 0000 0000 0000'
);

// Optional fields
$document->currency    = 'EUR';
$document->object      = 'Fornitura servizi';
$document->footNotes   = 'Grazie per la fiducia.';
$document->idNumerator = 42;
$document->idTemplate  = 10;
```

---

### `CustomerData`

```php
$customer = new CustomerData('Acme S.r.l.'); // CustomerName obbligatorio

$customer->customerAddress    = 'Via Roma, 1';
$customer->customerPostcode   = '20121';
$customer->customerCity       = 'Milano';
$customer->customerProvince   = 'MI';
$customer->customerCountry    = 'IT';
$customer->customerEmail      = 'info@acme.it';
$customer->customerVatCode    = '12345678910';
$customer->customerFiscalCode = 'RSSMRA80A01H501U';

// Electronic invoice delivery
$customer->feCustomerPec     = 'acme@pec.it';
$customer->feDestinationCode = 'ABCDEFG';
```

---

### `RowData`

```php
$row = new RowData(
    description: 'Visita medica',
    qty:         1,
    price:       1000.00, // prezzo unitario IVA esclusa
    vatCode:     22       // aliquota IVA (percentuale)
);

// Optional
$row->code           = 'SERV-001';
$row->um             = 'pz';
$row->vatDescription = '22%';
$row->discounts      = 0;
$row->idPdc          = 1234;

// Obbligatoria quando DocumentType = FE e vatCode = 0
$row->feVatNature = 'N4'; // Art. 10 — valid values: N1, N2.1 … N7
```

> `price` dev'essere il prezzo unitario **IVA esclusa**. Per prezzi basati sul peso, `qty` accetta valori con decimali (es.: `0.5` per 500g). Le quantità sono trasmesse con al massimo 2 cifre decimali; i numeri interi vengono presentati senza decimali (`1` non `1.00`). Gli importi in valuta vengono sempre presentati con 2 decimali.

---

### `PaymentData`

```php
$payment = new PaymentData(
    date:   '2025-03-31',
    amount: 1220.00, // VAT included
    paid:   false    // default
);
```

---

### `DeliveryData`

```php
$delivery = new DeliveryData();
$delivery->deliveryName     = 'Magazzino Acme';
$delivery->deliveryAddress  = 'Via Napoli, 150';
$delivery->deliveryPostcode = '20122';
$delivery->deliveryCity     = 'Milano';
$delivery->deliveryProvince = 'MI';
$delivery->deliveryCountry  = 'IT';
```

---

### `InvoiceData`

Aggrega tutti gli oggetti. Interfaccia flessibile per le sezioni facoltative.

```php
$invoice = (new InvoiceData($document, $customer, [$row1, $row2]))
    ->withDelivery($delivery)
    ->withPayments([$payment]);
```

---

## Metodi del client

### `testKey()`

Verifica la chiave API.

```php
$client->testKey();
```

### `saveDocument(InvoiceData $invoice, ?string $idRequest = null)`

Crea un documento. `$idRequest` è una chiave di idempotenza facoltativa — omettila in fase di sviluppo e test.

```php
$result = $client->saveDocument($invoice);
$result = $client->saveDocument($invoice, 'FE-' . $orderId); // con chiave idempotenza

// $result['docId']     — Fattura24 document ID
// $result['docNumber'] — document number (es.: '1/2025/FE')
// $result['raw']       — raw HTTP response
```

### `saveCustomer(CustomerData $customer)`

Crea o aggiorna un cliente in rubrica.

```php
$client->saveCustomer($customer);
```

### `getFile(string $docId)`

Scarica il file PDF del documento.

```php
$file = $client->getFile($result['docId']);

file_put_contents($file['filename'], $file['content']);
// $file['mime'] — es.: 'application/pdf'
```

### `getTemplates()`

```php
$templates = $client->getTemplates();
// $templates['invoice'][42] => 'Fattura standard (ID: 42)'
// $templates['order'][7]    => 'Ordine cliente (ID: 7)'
```

### `getNumerators()`

```php
$numerators = $client->getNumerators();
// $numerators['electronic_invoice'][5] => '2025/FE (Predefinito)'
// $numerators['invoice'][1]            => '2025/FA'
// $numerators['receipt'][3]            => '2025/RC'
```

### `getChartOfAccounts()`

```php
$coa = $client->getChartOfAccounts();
// $coa[1234] => '1.2.3 - Ricavi da servizi'
```

---

## Opzioni del client

```php
$client = new Fattura24Client([
    'apiKey'  => 'your-api-key',  // obbligatoria
    'source'  => 'my-app',        // facoltativa, identifica l'applicazione
    'timeout' => 60,              // facoltativo — timeout di cURL
]);
```

Il parametro `source` include automaticamente la versione SDK:

```
my-app SimplyIT-Fattura24SDK-1.0.0
```

---

## Gestione errori

Tutti gli errori sollevano eccezioni specifiche.

```php
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;
use SimplyIT\Fattura24SDK\Exceptions\ConnectionException;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;
use SimplyIT\Fattura24SDK\Exceptions\MissingApiKeyException;

try {
    $result = $client->saveDocument($invoice);
} catch (ValidationException $e) {
    // Dati non validi — prima della chiamata API
    echo $e->getMessage();
} catch (ConnectionException $e) {
    // errori Http o cURL
    echo "HTTP {$e->getHttpCode()}: {$e->getMessage()}";
} catch (Fattura24Exception $e) {
    // Altri errori
    echo $e->getMessage();
}
```

**Gerarchia delle eccezioni**

```
\RuntimeException
└── Fattura24Exception
    ├── MissingApiKeyException      chiave API non inserita
    ├── ValidationException         convalida dei dati fallita prima della chiamata API
    ├── ConnectionException         errore HTTP o cURL
    └── CurlNotInstalledException   ext-curl non installato
```

---

## Esecuzione dei test

```bash
composer install
./vendor/bin/phpunit                          # tutti i test
./vendor/bin/phpunit --testsuite Unit         # solo i test unitari
./vendor/bin/phpunit --testsuite Integration  # genera file XML campione
```

97 test, 286 asserzioni. I test unitari non richiedono chiamate di rete o chiavi API.

I test di integrazione generano file XML pronti per l'uso in `tests/Integration/output/` e possono essere inviati direttamente alle API di Fattura24 API tramite Postman o qualsiasi altro HTTP client.

---

## Esempio di integrazione WordPress

```php
use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\DocumentType;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\RowData;
use SimplyIT\Fattura24SDK\Data\InvoiceData;
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;

add_action('simplyit_fattura24_generate', function (array $data): void {

    $client = new Fattura24Client([
        'apiKey' => get_option('fattura24_api_key'),
        'source' => 'wp-' . parse_url(home_url(), PHP_URL_HOST),
    ]);

    // Prices must be VAT excluded — compute totals before building the objects
    $totalWithoutTax = $data['price'];                              // VAT excluded
    $vatAmount       = round($totalWithoutTax * $data['vat'], 2);
    $total           = round($totalWithoutTax + $vatAmount, 2);

    $document = new DocumentData(
        documentType:             DocumentType::FatturaElettronica,
        total:                    $total,
        totalWithoutTax:          $totalWithoutTax,
        vatAmount:                $vatAmount,
        sendEmail:                false,
        fePaymentCode:            'MP05',
        paymentMethodName:        get_option('fattura24_payment_name'),
        paymentMethodDescription: get_option('fattura24_payment_desc')
    );

    $customer = new CustomerData($data['customer_name']);
    $customer->customerEmail      = $data['customer_email'] ?? null;
    $customer->customerVatCode    = $data['customer_vat']   ?? null;
    $customer->feCustomerPec      = $data['customer_pec']   ?? null;
    $customer->feDestinationCode  = $data['customer_sdi']   ?? null;

    $row = new RowData($data['description'], 1, $totalWithoutTax, $data['vat']);

    try {
        $result = $client->saveDocument(
            new InvoiceData($document, $customer, [$row]),
            $data['id_request'] ?? null
        );

        do_action('simplyit_fattura24_generated', $result);

    } catch (Fattura24Exception $e) {
        do_action('simplyit_fattura24_error', $e, $data);
    }
});
```

---

## Struttura del progetto

```
src/
├── Fattura24Client.php         Entry point
├── Version.php                 SDK version
├── Api/
│   ├── HttpClient.php          cURL wrapper — content-type agnostic
│   └── Routes.php              API endpoint constants
├── Data/
│   ├── DocumentType.php        Backed enum of document types
│   ├── DocumentData.php
│   ├── CustomerData.php
│   ├── DeliveryData.php
│   ├── RowData.php
│   ├── PaymentData.php
│   └── InvoiceData.php
├── Handler/
│   └── ResponseHandler.php     Response parsing
├── Xml/
│   └── XmlGenerator.php        XML serialisation
└── Exceptions/
    ├── Fattura24Exception.php
    ├── MissingApiKeyException.php
    ├── ValidationException.php
    ├── ConnectionException.php
    └── CurlNotInstalledException.php
```

---

## Changelog

### 1.0.0
- Primo rilascio
- Requiito minimo PHP 8.1
- Oggetti con valori tipizzati per tutti i dati della richiesta (`DocumentData`, `CustomerData`, `RowData`, `PaymentData`, `DeliveryData`, `InvoiceData`)
- `DocumentType` lista
- `HttpClient` agnostico in relazione a content-type (form, multipart, JSON)
- Versione SDK automatica nel parametro `source`
- Parametro `IdRequest` facoltativo in `saveDocument()`
- 97 test — unitari e di integrazione

---

## Supporta il progetto
Se il progetto ti è utile:

[![Donate with PayPal](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/donate?hosted_button_id=H87RNJQ2VJTGY)

---

Maintained by **Simply IT**
L'informatica, semplicemente

---

## Licenza

MIT