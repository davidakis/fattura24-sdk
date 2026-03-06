# Usage Patterns & Best Practices

Pattern comuni e raccomandazioni per usare la SDK in modo efficace.

---

## DocumentData — Constructor Semplificato

### ✅ Pattern consigliato (minimalista)

```php
// Solo campi obbligatori + assegnazione esplicita
$document = new DocumentData(
    documentType: DocumentType::FatturaElettronica,
    total: 122.00,
);

// Imposta IVA esplicitamente (nessun calcolo automatico)
$document->totalWithoutTax = 100.00;
$document->vatAmount = 22.00;

// Default payment: MP08 (Pagamento con carta)
// Override solo se diverso:
$document->setPayment('MP05', 'Bonifico bancario');
```

**Perché questo approccio:**
- ✅ Esplicito — sai sempre cosa stai impostando
- ✅ Flessibile — controllo totale sui valori
- ✅ Zero magia — nessun calcolo nascosto
- ✅ Type-safe — named parameters prevengono errori

### ✅ Uso con tutti i defaults

```php
// Minimo assoluto
$document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
$document->totalWithoutTax = 100.00;
$document->vatAmount = 22.00;

// Usa defaults:
// - sendEmail: false
// - fePaymentCode: 'MP08'
// - paymentMethodName: 'Pagamento con carta'
// - paymentMethodDescription: ''
```

### ✅ Override defaults quando serve

```php
// Custom payment method
$document = new DocumentData(
    documentType: DocumentType::FatturaElettronica,
    total: 122.00,
    fePaymentCode: 'MP05',
    paymentMethodName: 'Bonifico bancario',
    paymentMethodDescription: 'IBAN: IT60X0542811101000000123456',
);
$document->totalWithoutTax = 100.00;
$document->vatAmount = 22.00;

// Enable email
$document->sendEmail = true;
```

### ✅ Fluent interface per payment

```php
$document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
$document->totalWithoutTax = 100.00;
$document->vatAmount = 22.00;
$document->setPayment('MP08', 'PayPal', 'Pagamento immediato PayPal');
```

---

## CustomerData — Validation esplicita

### ✅ Usa setters per campi validati

```php
$customer = new CustomerData('Mario Rossi');
$customer->customerCountry = 'IT';

// ✅ GIUSTO: Usa setter per validazione
$customer->setCustomerFiscalCode('RSSMRA80A01H501U');
$customer->setCustomerVatCode('12345678901');

// ❌ SBAGLIATO: Property access bypassa validazione
// $customer->customerFiscalCode = 'ABC'; // NO validation
```

**Regola pratica:**
- Campi validati (CF, P.IVA, PEC, SDI) → **Usa setters**
- Campi semplici (email, address) → Property access OK

### ✅ Validazione avviene solo per IT

```php
// Cliente italiano — validazione attiva
$customer->customerCountry = 'IT';
$customer->setCustomerFiscalCode('RSSMRA80A01H501U'); // ✓ Validato

// Cliente estero — validazione skippata
$customer->customerCountry = 'FR';
$customer->setCustomerFiscalCode('ABC123'); // ✓ Accettato (non-IT)
```

---

## InvoiceData — Multi-prodotto

### ✅ Invoice con più righe

```php
$rows = [
    new RowData('Consulenza base', 1, 100.00, 22),
    new RowData('Consulenza avanzata', 2, 150.00, 22),
    new RowData('Spese viaggio', 1, 50.00, 22),
];

$invoice = new InvoiceData($document, $customer, $rows);

// Totale calcolato dall'app (non dalla SDK):
// (100 * 1.22) + (150 * 2 * 1.22) + (50 * 1.22) = 549.00
$document->total = 549.00;
$document->totalWithoutTax = 450.00;
$document->vatAmount = 99.00;
```

### ✅ Invoice con shipping e fee

```php
use SimplyIT\Fattura24SDK\Data\DeliveryData;

$delivery = new DeliveryData(
    deliveryName: 'Ufficio Cliente',
    deliveryAddress: 'Via Roma 10',
    deliveryPostcode: '20100',
    deliveryCity: 'Milano',
    deliveryProvince: 'MI',
    deliveryCountry: 'IT',
);

$invoice = new InvoiceData($document, $customer, $rows);
$invoice->delivery = $delivery;

// Add shipping row
$invoice->rows[] = new RowData('Spese spedizione', 1, 10.00, 22);
```

---

## Payment Methods — Codici Fattura24

### Codici comuni

```php
// Carta di credito/debito (default SDK)
$document->fePaymentCode = 'MP08';
$document->paymentMethodName = 'Pagamento con carta';

// Bonifico bancario
$document->fePaymentCode = 'MP05';
$document->paymentMethodName = 'Bonifico bancario';

// PayPal / Stripe / Altri
$document->fePaymentCode = 'MP08'; // Usa MP08 per pagamenti elettronici
$document->paymentMethodName = 'PayPal';

// Contanti
$document->fePaymentCode = 'MP01';
$document->paymentMethodName = 'Contanti';

// Assegno
$document->fePaymentCode = 'MP02';
$document->paymentMethodName = 'Assegno';
```

### Helper method per payment

```php
// Invece di settare 3 properties
$document->setPayment('MP05', 'Bonifico', 'IBAN: IT...');

// Equivalente a:
// $document->fePaymentCode = 'MP05';
// $document->paymentMethodName = 'Bonifico';
// $document->paymentMethodDescription = 'IBAN: IT...';
```

---

## Error Handling — Patterns consigliati

### ✅ Catch specifico per validation

```php
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;

try {
    $customer->setCustomerFiscalCode($input);
} catch (ValidationException $e) {
    // Errore validazione input
    echo "Codice fiscale non valido: {$e->getMessage()}";
    // Mostra form con errore
} catch (Fattura24Exception $e) {
    // Errore generico SDK
    error_log($e->getMessage());
}
```

### ✅ Retry su network errors

```php
use SimplyIT\Fattura24SDK\Exceptions\ConnectionException;

$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        $response = $client->saveDocument($invoice);
        break; // Success
    } catch (ConnectionException $e) {
        $attempt++;
        if ($attempt >= $maxRetries) {
            throw $e;
        }
        sleep(2 ** $attempt); // Exponential backoff
    }
}
```

---

## Testing — Patterns consigliati

### ✅ Test con API key reale

```php
// test-manual.php (non committare)
$client = new Fattura24Client([
    'apiKey' => $_ENV['FATTURA24_API_KEY'],
    'source' => 'Test-Environment',
]);

// Crea invoice di test
$document = new DocumentData(DocumentType::FatturaElettronica, 122.00);
$document->totalWithoutTax = 100.00;
$document->vatAmount = 22.00;

$customer = new CustomerData('TEST - Cliente Fittizio');
$customer->customerEmail = 'test@example.com';
$customer->customerCountry = 'IT';

$row = new RowData('TEST - Articolo fittizio', 1, 100.00, 22);
$invoice = new InvoiceData($document, $customer, [$row]);

$response = $client->saveDocument($invoice);

echo "✓ Test invoice created: {$response->docNumber}\n";
echo "⚠️ Remember to delete from Fattura24 dashboard\n";
```

### ✅ Validation unit tests

```php
// No API call needed
$customer = new CustomerData('Test');
$customer->customerCountry = 'IT';

// Should throw
try {
    $customer->setCustomerFiscalCode('INVALID');
    $this->fail('Should have thrown ValidationException');
} catch (ValidationException $e) {
    $this->assertStringContainsString('16 caratteri', $e->getMessage());
}
```

---

## Performance — Best Practices

### ✅ Riusa client instance

```php
// ✅ GIUSTO: Una istanza per request lifecycle
class InvoiceService {
    private Fattura24Client $client;
    
    public function __construct() {
        $this->client = new Fattura24Client(['apiKey' => '...']);
    }
    
    public function createMultiple(array $invoices) {
        foreach ($invoices as $invoice) {
            $this->client->saveDocument($invoice); // Riusa connessione
        }
    }
}

// ❌ SBAGLIATO: Nuova istanza ogni volta
foreach ($invoices as $invoice) {
    $client = new Fattura24Client(['apiKey' => '...']); // Overhead!
    $client->saveDocument($invoice);
}
```

### ✅ Batch con rate limiting

```php
foreach ($invoices as $i => $invoice) {
    $response = $client->saveDocument($invoice);
    
    // Rate limit: 1 request ogni 0.5 secondi
    if ($i < count($invoices) - 1) {
        usleep(500000); // 500ms
    }
}
```

### ✅ Configura retry policy

```php
$client = new Fattura24Client(['apiKey' => '...']);
$client->setMaxRetries(5); // Default: 3
$client->setRetryDelay(2.0); // Default: 1.0 secondi
```

---

## Security — Best Practices

### ✅ API key da environment

```php
// ✅ GIUSTO
$client = new Fattura24Client([
    'apiKey' => $_ENV['FATTURA24_API_KEY'],
]);

// ❌ SBAGLIATO: Hardcoded
$client = new Fattura24Client([
    'apiKey' => 'abc123...', // NO!
]);
```

### ✅ Sanitizza input utente

```php
// Input da form
$fiscalCode = trim(strtoupper($_POST['cf']));

try {
    $customer->setCustomerFiscalCode($fiscalCode);
} catch (ValidationException $e) {
    // Validation failed — mostra errore
}
```

### ✅ PDF directory permissions

```php
$pdfDir = '/var/www/storage/invoices';

// Verifica permissions
if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0755, true);
}

if (!is_writable($pdfDir)) {
    throw new RuntimeException("PDF directory not writable: {$pdfDir}");
}

$client->setPdfDirectory($pdfDir);
```

---

## Regola pratica generale

| Scenario | Pattern consigliato |
|----------|---------------------|
| Creazione invoice semplice | Minimal constructor + property assignment |
| Validazione input utente | Usa setters espliciti |
| Batch processing | Riusa client + rate limiting |
| Multi-tenant | Client per tenant con directory separate |
| Testing | test-manual.php con API key vera |
| Production error handling | Catch specifico + retry policy |

**Principio guida:** Semplice quando puoi, esplicito quando devi, type-safe sempre.
