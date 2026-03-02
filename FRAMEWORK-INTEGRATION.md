# Framework Integration Examples

Come integrare la SDK Fattura24 in diversi framework e CMS.

---

## WordPress

### Setup base
```php
// functions.php o plugin init
add_action('init', function() {
    $client = new SimplyIT\Fattura24SDK\Fattura24Client([
        'apiKey' => get_option('fattura24_api_key'),
        'source' => 'MyPlugin',
    ]);
    
    // Configura save directory
    $upload_dir = wp_upload_dir();
    $pdf_dir = $upload_dir['basedir'] . '/invoices';
    
    if (!is_dir($pdf_dir)) {
        wp_mkdir_p($pdf_dir);
    }
    
    $client->setPdfDirectory($pdf_dir);
    
    // Configura URL generator per temp links
    $pdfManager = $client->getPdfManager(); // Assuming you expose this
    $pdfManager->setUrlGenerator(function($id) {
        return home_url("/download-invoice/{$id}");
    });
});
```

### Registra route per download temporanei
```php
add_action('init', function() {
    add_rewrite_rule(
        '^download-invoice/([^/]+)/?$',
        'index.php?invoice_download=$matches[1]',
        'top'
    );
});

add_filter('query_vars', function($vars) {
    $vars[] = 'invoice_download';
    return $vars;
});

add_action('template_redirect', function() {
    $id = get_query_var('invoice_download');
    if (!$id) return;
    
    // Serve temp file
    $tempDir = sys_get_temp_dir() . '/fattura24-temp';
    $file = $tempDir . '/' . $id . '.pdf';
    
    if (!file_exists($file)) {
        wp_die('File not found or expired', 404);
    }
    
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="invoice.pdf"');
    readfile($file);
    exit;
});
```

---

## Laravel

### Service Provider
```php
// app/Providers/Fattura24ServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use SimplyIT\Fattura24SDK\Fattura24Client;

class Fattura24ServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(Fattura24Client::class, function ($app) {
            $client = new Fattura24Client([
                'apiKey' => config('services.fattura24.api_key'),
                'source' => config('app.name'),
            ]);
            
            // Configure PDF directory
            $client->setPdfDirectory(storage_path('app/invoices'));
            
            // Configure URL generator
            $pdfManager = $client->getPdfManager();
            $pdfManager->setUrlGenerator(function($id) {
                return route('invoice.download.temp', ['id' => $id]);
            });
            
            return $client;
        });
    }
}
```

### Routes
```php
// routes/web.php
Route::get('/invoice/download/{id}', function($id) {
    $tempDir = sys_get_temp_dir() . '/fattura24-temp';
    $file = $tempDir . '/' . $id . '.pdf';
    
    if (!file_exists($file)) {
        abort(404, 'Invoice not found or expired');
    }
    
    return response()->file($file, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'inline; filename="invoice.pdf"',
    ]);
})->name('invoice.download.temp');
```

### Controller Usage
```php
namespace App\Http\Controllers;

use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\{DocumentData, DocumentType, CustomerData, RowData, InvoiceData};

class InvoiceController extends Controller
{
    public function __construct(
        private Fattura24Client $fattura24
    ) {}
    
    public function create(Request $request)
    {
        // Build invoice
        $document = new DocumentData(DocumentType::FE);
        $document->total = $request->input('total');
        
        $customer = new CustomerData($request->input('customer_name'));
        $customer->customerEmail = $request->input('customer_email');
        $customer->customerCountry = 'IT';
        
        $rows = collect($request->input('items'))->map(fn($item) => 
            new RowData($item['description'], $item['qty'], $item['price'], $item['vat'])
        )->toArray();
        
        $invoice = new InvoiceData($document, $customer, $rows);
        
        // Save
        $response = $this->fattura24->saveDocument($invoice);
        
        // Download PDF
        $pdfPath = $this->fattura24->downloadPdf($response->docId);
        
        return response()->json([
            'invoice_id' => $response->docId,
            'invoice_number' => $response->docNumber,
            'pdf_path' => $pdfPath,
        ]);
    }
}
```

---

## Symfony

### Service Configuration
```yaml
# config/services.yaml
services:
    SimplyIT\Fattura24SDK\Fattura24Client:
        arguments:
            $options:
                apiKey: '%env(FATTURA24_API_KEY)%'
                source: 'MySymfonyApp'
        calls:
            - setPdfDirectory: ['%kernel.project_dir%/var/invoices']
            - configurePdfManager: ['@fattura24.url_generator']
    
    fattura24.url_generator:
        class: Closure
        factory: ['@fattura24.url_generator_factory', 'create']
    
    fattura24.url_generator_factory:
        class: App\Service\Fattura24UrlGeneratorFactory
        arguments: ['@router']
```

### URL Generator Factory
```php
// src/Service/Fattura24UrlGeneratorFactory.php
namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Fattura24UrlGeneratorFactory
{
    public function __construct(
        private UrlGeneratorInterface $router
    ) {}
    
    public function create(): \Closure
    {
        return fn(string $id) => $this->router->generate(
            'invoice_download_temp',
            ['id' => $id],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
```

### Controller
```php
// src/Controller/InvoiceDownloadController.php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InvoiceDownloadController extends AbstractController
{
    #[Route('/invoice/download/{id}', name: 'invoice_download_temp')]
    public function downloadTemp(string $id): Response
    {
        $tempDir = sys_get_temp_dir() . '/fattura24-temp';
        $file = $tempDir . '/' . $id . '.pdf';
        
        if (!file_exists($file)) {
            throw $this->createNotFoundException('Invoice not found or expired');
        }
        
        return $this->file($file, 'invoice.pdf', ResponseHeaderBag::DISPOSITION_INLINE);
    }
}
```

---

## PrestaShop

### Module Configuration
```php
// modules/fattura24/fattura24.php
class Fattura24 extends Module
{
    private $client;
    
    public function __construct()
    {
        $this->name = 'fattura24';
        $this->tab = 'billing_invoicing';
        $this->version = '1.0.0';
        parent::__construct();
        
        $this->displayName = 'Fattura24 Integration';
        $this->description = 'Generate Italian electronic invoices';
    }
    
    public function install()
    {
        return parent::install() 
            && $this->registerHook('actionValidateOrder')
            && Configuration::updateValue('FATTURA24_API_KEY', '');
    }
    
    public function hookActionValidateOrder($params)
    {
        $order = $params['order'];
        
        // Initialize client
        $client = $this->getClient();
        
        // Build invoice from PrestaShop order
        $invoice = $this->buildInvoiceFromOrder($order);
        
        // Save
        $response = $client->saveDocument($invoice);
        
        // Download PDF
        $pdfPath = $client->downloadPdf($response->docId);
        
        // Attach to order
        $this->attachPdfToOrder($order->id, $pdfPath);
    }
    
    private function getClient()
    {
        if ($this->client) return $this->client;
        
        $this->client = new \SimplyIT\Fattura24SDK\Fattura24Client([
            'apiKey' => Configuration::get('FATTURA24_API_KEY'),
            'source' => 'PrestaShop',
        ]);
        
        // Configure PDF directory
        $pdfDir = _PS_MODULE_DIR_ . 'fattura24/invoices';
        if (!file_exists($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        $this->client->setPdfDirectory($pdfDir);
        
        // Configure URL generator
        $pdfManager = $this->client->getPdfManager();
        $pdfManager->setUrlGenerator(function($id) {
            return $this->context->link->getModuleLink(
                'fattura24',
                'download',
                ['id' => $id]
            );
        });
        
        return $this->client;
    }
}
```

---

## Vanilla PHP (No Framework)

```php
<?php
require 'vendor/autoload.php';

use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Data\{DocumentData, DocumentType, CustomerData, RowData, InvoiceData};

// Initialize client
$client = new Fattura24Client([
    'apiKey' => $_ENV['FATTURA24_API_KEY'],
    'source' => 'MyApp',
]);

// Configure PDF directory
$pdfDir = __DIR__ . '/storage/invoices';
if (!is_dir($pdfDir)) {
    mkdir($pdfDir, 0755, true);
}
$client->setPdfDirectory($pdfDir);

// Configure URL generator
$pdfManager = $client->getPdfManager();
$pdfManager->setUrlGenerator(function($id) {
    return "https://myapp.com/download.php?id={$id}";
});

// Create invoice
$document = new DocumentData(DocumentType::FE);
$document->total = 122.00;

$customer = new CustomerData('Mario Rossi');
$customer->customerEmail = 'mario@example.com';
$customer->customerCountry = 'IT';

$row = new RowData('Consulenza', 1, 100.00, 22);
$invoice = new InvoiceData($document, $customer, [$row]);

// Save and download
$response = $client->saveDocument($invoice);
$pdfPath = $client->downloadPdf($response->docId);

echo "Invoice created: {$response->docNumber}\n";
echo "PDF saved: {$pdfPath}\n";
```

### download.php (temp link handler)
```php
<?php
$id = $_GET['id'] ?? '';

if (!preg_match('/^[a-zA-Z0-9._-]+$/', $id)) {
    http_response_code(400);
    die('Invalid ID');
}

$tempDir = sys_get_temp_dir() . '/fattura24-temp';
$file = $tempDir . '/' . $id . '.pdf';

if (!file_exists($file)) {
    http_response_code(404);
    die('File not found or expired');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="invoice.pdf"');
header('Content-Length: ' . filesize($file));
readfile($file);
```

---

## Key Takeaways

1. **setUrlGenerator()** rimuove ogni accoppiamento framework-specific
2. **Ogni framework** può generare URL nel suo modo nativo
3. **Temp file route** deve essere registrato manualmente dall'applicazione
4. **Storage flexibility** — filesystem, cloud, database — app decide
