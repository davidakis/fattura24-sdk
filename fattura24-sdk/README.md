# SimplyIT Fattura24 SDK

PHP SDK for the [Fattura24](https://www.app.fattura24.com) API.

Designed to be embedded in any PHP project — custom applications, WordPress plugins, PrestaShop modules, Magento extensions — without coupling to any specific framework.

---

## Requirements

| | Minimum |
|---|---|
| PHP | 8.1 |
| ext-curl | any |
| ext-dom | any |
| ext-simplexml | any |

---

## Installation

```bash
composer require simplyit/fattura24-sdk
```

---

## Quick start

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

$row = new RowData('Consulenza', 1, 1000.00, 22); // price is VAT excluded

$result = $client->saveDocument(new InvoiceData($document, $customer, [$row]));

echo $result['docId'];     // Fattura24 document ID
echo $result['docNumber']; // e.g. 1/2025/FE
```

---

## Data objects

The SDK uses typed value objects to represent the request payload. All validation happens at construction time, before any API call is made.

> **Note on prices and totals**
> The Fattura24 API expects all amounts VAT excluded (`price`, `totalWithoutTax`) and the VAT amount as a separate currency value (`vatAmount`). The `total` field is the grand total VAT included. The SDK does not perform any calculation — it serializes exactly the values you provide. Make sure to compute and pass the correct figures before building the data objects.

---

### `DocumentType`

Backed enum of known document types. Use the cases directly.

| Case | Value |
|---|---|
| `DocumentType::FatturaAccompagatoria` | `C` |
| `DocumentType::FatturaElettronica` | `FE` |
| `DocumentType::Fattura` | `I` |
| `DocumentType::FatturaForce` | `I-Force` |
| `DocumentType::Ricevuta` | `R` |

To convert from a raw string: `DocumentType::from('FE')`.
For unknown types (future Fattura24 additions): `DocumentType::tryFrom('XYZ')` returns `null` instead of throwing.

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
$customer = new CustomerData('Acme S.r.l.'); // CustomerName is required

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
    price:       1000.00, // unit price, VAT excluded
    vatCode:     22       // VAT rate as integer percentage
);

// Optional
$row->code           = 'SERV-001';
$row->um             = 'pz';
$row->vatDescription = '22%';
$row->discounts      = 0;
$row->idPdc          = 1234;

// Required when DocumentType = FE and vatCode = 0
$row->feVatNature = 'N4'; // Art. 10 — valid values: N1, N2.1 … N7
```

> `price` must be the unit price **VAT excluded**. For weight-based billing, `qty` accepts float values (e.g. `0.5` for 500g). Quantities are serialized with up to 2 decimal places; whole numbers are serialized without decimals (`1` not `1.00`). Monetary amounts are always serialized with exactly 2 decimal places.

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

Aggregates all objects. Fluent interface for optional sections.

```php
$invoice = (new InvoiceData($document, $customer, [$row1, $row2]))
    ->withDelivery($delivery)
    ->withPayments([$payment]);
```

---

## Client methods

### `testKey()`

Verifies the API key.

```php
$client->testKey();
```

### `saveDocument(InvoiceData $invoice, ?string $idRequest = null)`

Creates a document. `$idRequest` is an optional idempotency key — omit it during development and testing.

```php
$result = $client->saveDocument($invoice);
$result = $client->saveDocument($invoice, 'FE-' . $orderId); // with idempotency key

// $result['docId']     — Fattura24 document ID
// $result['docNumber'] — document number (e.g. '1/2025/FE')
// $result['raw']       — raw HTTP response
```

### `saveCustomer(CustomerData $customer)`

Creates or updates a customer record.

```php
$client->saveCustomer($customer);
```

### `getFile(string $docId)`

Downloads the document file (PDF or SDI XML).

```php
$file = $client->getFile($result['docId']);

file_put_contents($file['filename'], $file['content']);
// $file['mime'] — e.g. 'application/pdf'
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

## Client options

```php
$client = new Fattura24Client([
    'apiKey'  => 'your-api-key',  // required
    'source'  => 'my-app',        // optional — your application name
    'timeout' => 60,              // optional — cURL timeout in seconds, default 60
]);
```

The `source` parameter is composed automatically with the SDK version identifier and sent in every API call:

```
my-app SimplyIT-Fattura24SDK/1.0.0
```

---

## Error handling

All errors throw exceptions. No silent failures.

```php
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;
use SimplyIT\Fattura24SDK\Exceptions\ConnectionException;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;
use SimplyIT\Fattura24SDK\Exceptions\MissingApiKeyException;

try {
    $result = $client->saveDocument($invoice);
} catch (ValidationException $e) {
    // Invalid data — caught before the API call
    echo $e->getMessage();
} catch (ConnectionException $e) {
    // HTTP or cURL error
    echo "HTTP {$e->getHttpCode()}: {$e->getMessage()}";
} catch (Fattura24Exception $e) {
    // Any other SDK error
    echo $e->getMessage();
}
```

**Exception hierarchy**

```
\RuntimeException
└── Fattura24Exception
    ├── MissingApiKeyException      apiKey not provided
    ├── ValidationException         data failed validation before the API call
    ├── ConnectionException         HTTP or cURL failure
    └── CurlNotInstalledException   ext-curl not available
```

---

## Running the tests

```bash
composer install
./vendor/bin/phpunit                          # all tests
./vendor/bin/phpunit --testsuite Unit         # unit tests only
./vendor/bin/phpunit --testsuite Integration  # generates sample XML files
```

97 tests, 286 assertions. Unit tests require no network calls or API credentials.

Integration tests generate ready-to-use XML files in `tests/Integration/output/` that can be submitted directly to the Fattura24 API via Postman or any HTTP client for format validation.

---

## WordPress integration example

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
    $vatAmount       = round($totalWithoutTax * $data['vat'] / 100, 2);
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

## Project structure

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
- First release
- PHP 8.1 minimum requirement
- Typed value objects for all request data (`DocumentData`, `CustomerData`, `RowData`, `PaymentData`, `DeliveryData`, `InvoiceData`)
- `DocumentType` backed enum
- `HttpClient` agnostic to content-type (form, multipart, JSON)
- Automatic SDK version in `source` parameter
- Optional `IdRequest` parameter on `saveDocument()`
- 97 tests — unit and integration

---

## License

MIT