# Esempi Avanzati

Casi d'uso reali e pattern consigliati.

---

## 1. Download PDF con gestione errori

```php
use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;

$client = new Fattura24Client([
    'apiKey' => $_ENV['FATTURA24_API_KEY'],
    'pdfDir' => '/var/www/storage/invoices',
]);

try {
    // Scarica PDF
    $file = $client->getFile($docId);
    
    // Verifica che sia un PDF valido
    if (!$file->isPdf()) {
        throw new \RuntimeException("File non è un PDF: {$file->contentType}");
    }
    
    // Verifica dimensione
    if ($file->isEmpty()) {
        throw new \RuntimeException("PDF vuoto");
    }
    
    if ($file->size > 10 * 1024 * 1024) {
        throw new \RuntimeException("PDF troppo grande: {$file->getHumanSize()}");
    }
    
    // Salva con PdfManager
    $path = $client->downloadPdf($docId);
    
    echo "✓ PDF salvato: {$path} ({$file->getHumanSize()})\n";
    
} catch (Fattura24Exception $e) {
    error_log("Errore API Fattura24: {$e->getMessage()}");
    // Retry o notifica admin
} catch (\RuntimeException $e) {
    error_log("Errore validazione PDF: {$e->getMessage()}");
}
```

---

## 2. Batch processing con retry

```php
$invoices = [
    ['customer' => 'Mario Rossi', 'amount' => 100],
    ['customer' => 'Luigi Verdi', 'amount' => 200],
    // ... 100 invoices
];

$client = new Fattura24Client(['apiKey' => $_ENV['API_KEY']]);
$client->setMaxRetries(5); // Retry per network hiccups

$results = [];
$errors = [];

foreach ($invoices as $data) {
    try {
        // Build invoice
        $invoice = buildInvoiceFromData($data);
        
        // Send
        $response = $client->saveDocument($invoice);
        
        // Download PDF
        $file = $client->getFile($response->docId);
        
        $results[] = [
            'customer' => $data['customer'],
            'doc_id' => $response->docId,
            'doc_number' => $response->docNumber,
            'pdf_size' => $file->getHumanSize(),
        ];
        
        // Rate limiting
        usleep(500000); // 0.5 seconds between requests
        
    } catch (\Exception $e) {
        $errors[] = [
            'customer' => $data['customer'],
            'error' => $e->getMessage(),
        ];
    }
}

// Report
echo "✓ Successo: " . count($results) . "\n";
echo "✗ Errori: " . count($errors) . "\n";

if (!empty($errors)) {
    file_put_contents('errors.json', json_encode($errors, JSON_PRETTY_PRINT));
}
```

---

## 3. Webhook per download asincrono

```php
// webhook-handler.php
// Riceve notifica da sistema esterno che fattura è stata creata

$docId = $_POST['doc_id'] ?? '';

if (!$docId) {
    http_response_code(400);
    exit('Missing doc_id');
}

$client = new Fattura24Client([
    'apiKey' => $_ENV['API_KEY'],
    'pdfDir' => '/var/www/storage/invoices',
]);

try {
    // Download PDF in background
    $file = $client->getFile($docId);
    $path = $client->downloadPdf($docId);
    
    // Notifica via email
    mail(
        'admin@example.com',
        'PDF Fattura disponibile',
        "PDF salvato: {$path}\nDimensione: {$file->getHumanSize()}"
    );
    
    // Response 200
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'path' => $path,
        'size' => $file->size,
    ]);
    
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
```

---

## 4. Multi-tenancy con directory separate

```php
class TenantInvoiceService
{
    private array $clients = [];
    
    public function getClientForTenant(string $tenantId): Fattura24Client
    {
        if (!isset($this->clients[$tenantId])) {
            $tenant = $this->loadTenant($tenantId);
            
            $client = new Fattura24Client([
                'apiKey' => $tenant['api_key'],
                'source' => "Tenant-{$tenantId}",
            ]);
            
            // Ogni tenant ha la sua directory
            $pdfDir = "/var/www/storage/tenants/{$tenantId}/invoices";
            if (!is_dir($pdfDir)) {
                mkdir($pdfDir, 0755, true);
            }
            $client->setPdfDirectory($pdfDir);
            
            // Custom URL generator
            $client->getPdfManager()->setUrlGenerator(
                fn($id) => url("/tenant/{$tenantId}/download/{$id}")
            );
            
            $this->clients[$tenantId] = $client;
        }
        
        return $this->clients[$tenantId];
    }
    
    public function createInvoiceForTenant(string $tenantId, InvoiceData $invoice): array
    {
        $client = $this->getClientForTenant($tenantId);
        
        $response = $client->saveDocument($invoice);
        $file = $client->getFile($response->docId);
        $path = $client->downloadPdf($response->docId);
        
        return [
            'doc_id' => $response->docId,
            'doc_number' => $response->docNumber,
            'pdf_path' => $path,
            'pdf_size' => $file->getHumanSize(),
        ];
    }
}
```

---

## 5. Archiviazione PDF con metadata database

```php
class InvoiceArchiver
{
    public function __construct(
        private Fattura24Client $client,
        private PDO $db
    ) {}
    
    public function archiveInvoice(string $docId): int
    {
        // Download PDF
        $file = $this->client->getFile($docId);
        $path = $this->client->downloadPdf($docId);
        
        // Salva metadata in database
        $stmt = $this->db->prepare("
            INSERT INTO invoices (doc_id, filename, filepath, size, content_type, created_at)
            VALUES (:doc_id, :filename, :filepath, :size, :content_type, NOW())
        ");
        
        $stmt->execute([
            'doc_id' => $docId,
            'filename' => $file->filename,
            'filepath' => $path,
            'size' => $file->size,
            'content_type' => $file->contentType,
        ]);
        
        return (int) $this->db->lastInsertId();
    }
    
    public function getArchivedInvoice(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

// Uso
$archiver = new InvoiceArchiver($client, $pdo);
$id = $archiver->archiveInvoice($docId);
echo "Fattura archiviata con ID: {$id}\n";
```

---

## 6. Validazione pre-invio

```php
class InvoiceValidator
{
    public function validate(InvoiceData $invoice): array
    {
        $errors = [];
        
        // Verifica customer
        if (empty($invoice->customer->customerName)) {
            $errors[] = "Nome cliente mancante";
        }
        
        if ($invoice->customer->customerCountry === 'IT') {
            if (empty($invoice->customer->customerFiscalCode) && 
                empty($invoice->customer->customerVatCode)) {
                $errors[] = "CF o P.IVA obbligatori per clienti IT";
            }
        }
        
        // Verifica totale
        $calculatedTotal = 0;
        foreach ($invoice->rows as $row) {
            $calculatedTotal += $row->price * $row->qty * (1 + $row->vatCode / 100);
        }
        
        if (abs($calculatedTotal - $invoice->document->total) > 0.01) {
            $errors[] = "Totale non corrisponde: dichiarato {$invoice->document->total}, calcolato {$calculatedTotal}";
        }
        
        return $errors;
    }
}

// Uso
$validator = new InvoiceValidator();
$errors = $validator->validate($invoice);

if (!empty($errors)) {
    echo "✗ Errori validazione:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    exit(1);
}

// OK, invia
$response = $client->saveDocument($invoice);
```

---

## 7. Monitor dimensione PDF

```php
class PdfSizeMonitor
{
    private array $stats = [];
    
    public function track(GetFileResponse $file, string $docId): void
    {
        $this->stats[] = [
            'doc_id' => $docId,
            'filename' => $file->filename,
            'size' => $file->size,
            'human_size' => $file->getHumanSize(),
            'timestamp' => time(),
        ];
    }
    
    public function getAverageSize(): float
    {
        if (empty($this->stats)) return 0;
        
        $total = array_sum(array_column($this->stats, 'size'));
        return $total / count($this->stats);
    }
    
    public function getLargest(): ?array
    {
        if (empty($this->stats)) return null;
        
        return array_reduce($this->stats, function($max, $stat) {
            return $max === null || $stat['size'] > $max['size'] ? $stat : $max;
        });
    }
    
    public function report(): void
    {
        echo "📊 PDF Size Report\n";
        echo "Total files: " . count($this->stats) . "\n";
        echo "Average size: " . $this->formatBytes($this->getAverageSize()) . "\n";
        
        $largest = $this->getLargest();
        if ($largest) {
            echo "Largest: {$largest['filename']} ({$largest['human_size']})\n";
        }
    }
    
    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB'];
        $unit = 0;
        while ($bytes >= 1024 && $unit < 2) {
            $bytes /= 1024;
            $unit++;
        }
        return round($bytes, 2) . ' ' . $units[$unit];
    }
}

// Uso
$monitor = new PdfSizeMonitor();

foreach ($docIds as $docId) {
    $file = $client->getFile($docId);
    $monitor->track($file, $docId);
}

$monitor->report();
```

---

## Regola pratica: Quando usare GetFileResponse

✅ **USA quando:**
- Devi verificare metadata prima del salvataggio
- Archivi info in database
- Validazioni sul file (dimensione, tipo)
- Monitoring/logging

❌ **NON SERVE quando:**
- Scarichi e basta (`downloadPdf()` è sufficiente)
- Non ti interessa metadata
- Workflow semplice

```php
// Semplice (OK)
$path = $client->downloadPdf($docId);

// Avanzato (quando serve metadata)
$file = $client->getFile($docId);
if ($file->size > $maxSize) {
    // Handle large file
}
$path = $client->downloadPdf($docId, $file->filename);
```
