# Fattura24 PHP SDK

PHP SDK tipizzato e testato per l'integrazione con le API di [Fattura24](https://www.fattura24.com/api/introduzione/).

Progettato per applicazioni personalizzate, plugin WordPress, moduli e-commerce e sistemi gestionali - senza accoppiamento a framework o piattaforme specifiche.

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://www.php.net)
![Latest Version](https://img.shields.io/packagist/v/simplyit/fattura24-sdk)
](https://packagist.org/packages/simplyit/fattura24-sdk)
[
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

---

## 🚨 v2.0 Released - Breaking Changes

**v2.0** è una riscrittura completa con response objects tipizzati e PHP 8.1+ requirement.

**Stai aggiornando da v1.x?** Leggi [UPGRADE.md](UPGRADE.md)  
**Ti serve PHP 7.4?** Usa [v1-legacy branch](https://github.com/davidakis/fattura24-sdk/tree/v1-legacy) (solo security fixes)

---

## ✨ Caratteristiche

✅ **PHP 8.1+** con named parameters e readonly properties  
✅ **Response objects tipizzati**  
✅ **Validazione automatica** dati fiscali italiani (CF, P.IVA, PEC, SDI)  
✅ **Generazione XML automatica** conforme alle specifiche Fattura24  
✅ **HTTP retry logic** con exponential backoff  
✅ **Framework-agnostic** — nessun accoppiamento a WordPress, Laravel, Symfony, etc.  
✅ **100% test coverage** con PHPUnit  
✅ **PHPStan Level 6** — static analysis completa

---

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
composer require davidakis/fattura24-sdk
```

### Senza Composer

1. Scarica l'[ultima versione](https://github.com/davidakis/fattura24-sdk/releases/latest) dal repository GitHub
2. Estrai la cartella `src/` all'interno del tuo progetto
3. Includi un semplice autoloader PSR-4 oppure utilizza il tuo autoloader esistente

Esempio di autoloader minimale:

```php
spl_autoload_register(function ($class) {
    $prefix = 'SimplyIT\\Fattura24SDK\\';
    $baseDir = __DIR__ . '/src/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
```

Assicurati che le estensioni richieste (`ext-curl`, `ext-dom`, `ext-simplexml`) siano abilitate nel tuo `php.ini`.

---

## 🚀 Quick Start

```php
use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\{DocumentData, DocumentType, CustomerData, RowData, InvoiceData};

// 1. Crea il client
$client = new Fattura24Client([
    'apiKey' => 'your-api-key',
    'source' => 'MyApp',  // opzionale
    'pdfDir' => '/var/www/fatture', // opzionale: salva PDF qui
]);

// 2. Prepara i dati
$document = new DocumentData(
    documentType: DocumentType::FE,
    total: 122.00,
);
$document->totalWithoutTax = 100.00;
$document->vatAmount = 22.00;

// Optional: override default payment (default is MP08 - Pagamento con carta)
$document->setPayment('MP05', 'Bonifico bancario');

$customer = new CustomerData('Mario Rossi');
$customer->customerCountry = 'IT';
$customer->setCustomerFiscalCode('RSSMRA80A01H501U'); // Auto-validato per clienti IT
$customer->customerEmail = 'mario@email.it';

$row = new RowData('Consulenza', 1, 100.00, 22);

$invoice = new InvoiceData($document, $customer, [$row]);

// 3. Invia la fattura
$response = $client->saveDocument($invoice);

echo "Fattura #{$response->docNumber} creata con ID {$response->docId}\n";
```

---

## 📖 Documentazione

- [Guida Upgrade v1→v2](UPGRADE.md)
- [Changelog Completo](CHANGELOG.md)
- [Framework Integration](FRAMEWORK-INTEGRATION.md) (WordPress, Laravel, Symfony, PrestaShop)
- [Advanced Examples](ADVANCED-EXAMPLES.md)
- [Usage Patterns](USAGE-PATTERNS.md)

---

## 💡 Features v2.0

### Response Objects Tipizzati

```php
// SaveDocumentResponse
$response = $client->saveDocument($invoice);
echo $response->docId;      // string - IDE autocomplete
echo $response->docNumber;  // string - Type-safe
$response->isSuccess();     // bool - Helper method

// GetFileResponse con metadata
$file = $client->getFile($docId);
echo $file->filename;       // "invoice_123.pdf"
echo $file->contentType;    // "application/pdf"
echo $file->getHumanSize(); // "1.5 MB"

if ($file->isPdf()) {
    file_put_contents('/tmp/invoice.pdf', $file->content);
}

// GetTemplatesResponse
$templates = $client->getTemplates();
foreach ($templates->invoice as $id => $name) {
    echo "<option value='{$id}'>{$name}</option>";
}

// GetNumeratorsResponse con helper
$numerators = $client->getNumerators();
$defaultId = $numerators->getDefaultId('invoice');
$document->idNumerator = $defaultId;

// GetChartOfAccountsResponse con search
$pdc = $client->getChartOfAccounts();
$filtered = $pdc->search('prodotto'); // Case-insensitive
foreach ($filtered as $id => $desc) {
    echo "{$id}: {$desc}\n";
}
```

**Architettura:** `XML → ResponseHandler → Response Object` (conversione diretta, zero overhead)

---

### Validazione Automatica (solo Italia)

```php
$customer = new CustomerData('Rossi SRL');
$customer->customerCountry = 'IT';

// ✓ OK: 11 cifre
$customer->setCustomerVatCode('12345678901');

// ✗ Exception: formato non valido
$customer->setCustomerVatCode('ABC');
// InvalidArgumentException: P.IVA italiana deve essere 11 cifre numeriche

// ✓ OK: validazione solo per IT
$customer->customerCountry = 'FR';
$customer->setCustomerVatCode('FR123');  // Non validato (cliente estero)
```

**Campi validati (solo per customerCountry = 'IT'):**
- `customerFiscalCode` — 16 caratteri alfanumerici
- `customerVatCode` — 11 cifre numeriche
- `feCustomerPec` — formato email valido
- `feDestinationCode` — 6 o 7 caratteri alfanumerici

**Sanitizzazione automatica:**
```php
$customer->setCustomerFiscalCode(' rssmra80a01h501u ');
// Salvato come: 'RSSMRA80A01H501U' (trim + uppercase)
```

---

### Gestione PDF Flessibile

**Salva su file:**
```php
$client->setPdfDirectory('/var/www/fatture');
$filepath = $client->downloadPdf($docId);
// Returns: /var/www/fatture/invoice_123.pdf
```

**Stream al browser:**
```php
$client->setPdfDirectory(null);
$result = $client->downloadPdf($docId);
// PDF streamed direttamente (usando readfile())
// Returns: null (PDF già inviato)
```

**Link temporaneo (framework-agnostic):**
```php
$pdfManager = $client->getPdfManager();

// WordPress
$pdfManager->setUrlGenerator(fn($id) => home_url("/pdf/{$id}"));

// Laravel  
$pdfManager->setUrlGenerator(fn($id) => route('pdf.download', ['id' => $id]));

// Symfony
$pdfManager->setUrlGenerator(fn($id) => $router->generate('pdf_download', ['id' => $id]));

// Vanilla PHP
$pdfManager->setUrlGenerator(fn($id) => "https://example.com/download.php?id={$id}");
```

Vedi [FRAMEWORK-INTEGRATION.md](FRAMEWORK-INTEGRATION.md) per esempi completi.

---

### Named Parameters (PHP 8.1)

```php
// Compatto (posizionali)
$row = new RowData('Servizio', 1, 100.00, 22);

// Esplicito (named parameters) - raccomandato
$row = new RowData(
    description: 'Servizio di consulenza',
    qty: 1,
    price: 100.00,
    vatCode: 22,
);

// DocumentData semplificato (solo 2 params obbligatori)
$document = new DocumentData(
    documentType: DocumentType::FE,
    total: 122.00,
);
// Default payment: MP08 (Pagamento con carta)
```

---

### Fluent Interface

```php
$invoice = (new InvoiceData($document, $customer, [$row]))
    ->withDelivery($delivery)
    ->withPayments([$payment]);

$document->setPayment('MP05', 'Bonifico bancario', 'IBAN: IT...');
```

---

## 📝 Esempi Completi

### Fattura con IVA 0% (esente)

```php
$document = new DocumentData(
    documentType: DocumentType::FE,
    total: 100.00,
);
$document->totalWithoutTax = 100.00;
$document->vatAmount = 0.00;

$customer = new CustomerData('Studio Medico Bianchi');
$customer->customerCountry = 'IT';
$customer->setCustomerVatCode('12345678901');
$customer->feDestinationCode = '0000000';
$customer->feCustomerPec = 'studio@pec.it';

$row = new RowData('Visita specialistica', 1, 100.00, 0);
$row->feVatNature = 'N4'; // Esente art. 10

$invoice = new InvoiceData($document, $customer, [$row]);
$response = $client->saveDocument($invoice);
```

---

### Fattura con sconto

```php
$document = new DocumentData(
    documentType: DocumentType::FE,
    total: 109.80,
);
$document->totalWithoutTax = 90.00;
$document->vatAmount = 19.80;

$row = new RowData('Prodotto', 1, 100.00, 22);
$row->discount = 10; // Sconto 10%

$invoice = new InvoiceData($document, $customer, [$row]);
```

---

### Fattura con prodotti multipli e IVA miste

```php
$rows = [
    new RowData('Bene essenziale', 1, 100.00, 10),  // IVA 10%
    new RowData('Servizio standard', 1, 100.00, 22), // IVA 22%
];

$document->total = 132.00;
$document->totalWithoutTax = 110.00;
$document->vatAmount = 22.00; // 10 + 22

$invoice = new InvoiceData($document, $customer, $rows);
```

---

### Test connessione

```php
$result = $client->testKey();
// ['returnCode' => 0, 'message' => 'OK']
```

---

## 🔄 Upgrade da v1.x

### BREAKING CHANGE: Response Type

**v1.x (array):**
```php
$result = $client->saveDocument($invoice);
$docId = $result['docId'];
$docNumber = $result['docNumber'];
```

**v2.0 (typed object):**
```php
$response = $client->saveDocument($invoice);
$docId = $response->docId;
$docNumber = $response->docNumber;
```

### Migration Path

```php
// Backward compatible wrapper (se necessario)
function saveDocumentLegacy($client, $invoice) {
    $response = $client->saveDocument($invoice);
    return [
        'docId' => $response->docId,
        'docNumber' => $response->docNumber,
        'docType' => $response->docType,
    ];
}
```

**Guida completa:** [UPGRADE.md](UPGRADE.md)

---

## 🧪 Sviluppo

```bash
# Setup
composer install

# Run tests
composer test

# Code style
composer cs-fix      # Fix issues
composer cs-check    # Check only

# Static analysis
composer phpstan

# Run all checks
composer check-all
```

---

## 🎯 Perché PHP 8.1+?

Questa SDK sfrutta le moderne feature di PHP per una migliore developer experience:

| Feature | Beneficio |
|---------|-----------|
| **Named parameters** | Codice auto-documentante, niente errori di posizione |
| **Readonly properties** | Dati immutabili, codice più sicuro |
| **Enums** | Costanti type-safe (DocumentType, etc.) |
| **Union types** | Flessibilità con type safety |
| **Constructor promotion** | Meno boilerplate |

**Ti serve PHP 7.4?** Usa [v1.x branch](https://github.com/davidakis/fattura24-sdk/tree/v1-legacy) (solo security fixes per 12 mesi)

---

## 💖 Supporta il progetto

Se questa SDK ti fa risparmiare tempo e semplifica il tuo lavoro:

[![Donate with PayPal](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/donate?hosted_button_id=H87RNJQ2VJTGY)

Il tuo supporto aiuta a mantenere e migliorare questo progetto. Grazie! ❤️

---

## 📄 Licenza

MIT License. Vedi [LICENSE](LICENSE) per dettagli.

---

## 🙏 Credits

- **Fattura24** per le API
- **Developer:** [Simply IT](https://github.com/davidakis)
- **Contributors:** [Tutti i contributors](https://github.com/davidakis/fattura24-sdk/graphs/contributors)

---

## 📞 Supporto

- 🐛 [Segnala Issues](https://github.com/davidakis/fattura24-sdk/issues)
- 💬 [Discussions](https://github.com/davidakis/fattura24-sdk/discussions)
- 📧 Docs ufficiali: https://docs.fattura24.com

---

## ⚠️ Disclaimer

**Non affiliato a Fattura24.**  
Questa è una SDK mantenuta dalla community per le API di Fattura24.

Per supporto ufficiale Fattura24, visita [www.fattura24.com](https://www.fattura24.com)

---

**Maintained by Simply IT**  
*L'informatica, semplicemente*

[![Simply IT](https://img.shields.io/badge/Simply-IT-blue)](https://github.com/davidakis)