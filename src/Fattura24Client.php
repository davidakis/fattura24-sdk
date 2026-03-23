<?php

namespace SimplyIT\Fattura24SDK;

use InvalidArgumentException;
use RuntimeException;
use SimplyIT\Fattura24SDK\Api\HttpClient;
use SimplyIT\Fattura24SDK\Api\Routes;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\DocumentType;
use SimplyIT\Fattura24SDK\Data\InvoiceData;
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;
use SimplyIT\Fattura24SDK\Exceptions\MissingApiKeyException;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;
use SimplyIT\Fattura24SDK\Handler\PdfManager;
use SimplyIT\Fattura24SDK\Handler\ResponseHandler;
use SimplyIT\Fattura24SDK\Log\LoggerInterface;
use SimplyIT\Fattura24SDK\Log\NullLogger;
use SimplyIT\Fattura24SDK\Response\GetChartOfAccountsResponse;
use SimplyIT\Fattura24SDK\Response\GetFileResponse;
use SimplyIT\Fattura24SDK\Response\GetNumeratorsResponse;
use SimplyIT\Fattura24SDK\Response\GetTemplatesResponse;
use SimplyIT\Fattura24SDK\Response\SaveDocumentResponse;
use SimplyIT\Fattura24SDK\Response\TestKeyResponse;
use SimplyIT\Fattura24SDK\Xml\XmlGenerator;
use Throwable;

/**
 * Fattura24Client
 *
 * Main entry point for the SDK. Owns:
 *  - Authentication (apiKey, source injection into every request)
 *  - Request body encoding (http_build_query for form requests)
 *  - Orchestration of XmlGenerator → HttpClient → ResponseHandler
 *  - PDF download management
 *  - Pre-send normalization of InvoiceData (normalizeInvoice)
 *
 * HttpClient is kept completely agnostic: it receives a ready body
 * and an array of HTTP headers, nothing more.
 *
 * @version 2.2.0
 */
class Fattura24Client
{
    private string $apiKey;
    private string $source;
    private HttpClient $http;
    private XmlGenerator $xml;
    private ResponseHandler $handler;
    private PdfManager $pdfManager;
    private LoggerInterface $logger;

    /**
     * @param array $options
     *                       - apiKey  (string,          required)
     *                       - source  (string,          optional)  Your app name. Will be composed with the SDK
     *                       version identifier, e.g. "my-app SimplyIT-Fattura24SDK/2.0.0".
     *                       If omitted, only the SDK identifier is sent.
     *                       - timeout (int,             optional)  cURL timeout in seconds. Default: 60.
     *                       - pdfDir  (string,          optional)  Directory to save downloaded PDFs. If null, PDFs output to browser.
     *                       - logger  (LoggerInterface, optional)  Logger instance. If omitted, logging is disabled (NullLogger).
     *
     * @throws MissingApiKeyException
     */
    public function __construct(array $options = [], ?HttpClient $httpClient = null)
    {
        if (empty($options['apiKey'])) {
            throw new MissingApiKeyException('apiKey is required to instantiate Fattura24Client.');
        }

        $this->apiKey  = $options['apiKey'];

        $appSource     = $options['source'] ?? null;
        $sdkIdentifier = Version::identifier();
        $this->source  = $appSource
            ? "{$appSource} {$sdkIdentifier}"
            : $sdkIdentifier;
        $timeout       = $options['timeout'] ?? 60;

        $this->http       = $httpClient ?? new HttpClient($timeout);
        $this->xml        = new XmlGenerator();
        $this->handler    = new ResponseHandler();
        $this->pdfManager = new PdfManager();
        $logger           = $options['logger'] ?? null;
        $this->logger     = $logger instanceof LoggerInterface
            ? $logger
            : new NullLogger();

        if (!empty($options['pdfDir'])) {
            $this->pdfManager->setSaveDirectory($options['pdfDir']);
        }

        $this->logger->debug('Fattura24Client inizializzato', [
            'source'  => $this->source,
            'timeout' => $timeout,
        ]);
    }

    /**
     * Sets the directory where downloaded PDFs will be saved.
     * Pass null to output PDFs to browser instead.
     *
     * @param string|null $directory Absolute path to directory
     * @throws InvalidArgumentException if directory invalid
     */
    public function setPdfDirectory(?string $directory): void
    {
        $this->pdfManager->setSaveDirectory($directory);
    }

    /**
     * Gets the current PDF save directory, or null if outputting to browser.
     */
    public function getPdfDirectory(): ?string
    {
        return $this->pdfManager->getSaveDirectory();
    }

    /**
     * Gets the PdfManager instance for advanced configuration.
     *
     * Use this to customize URL generation for temporary downloads:
     * ```php
     * $client->getPdfManager()->setUrlGenerator(fn($id) => route('pdf', ['id' => $id]));
     * ```
     */
    public function getPdfManager(): PdfManager
    {
        return $this->pdfManager;
    }

    /**
     * Returns the active logger instance.
     * Useful for injecting the same logger in other components (e.g. SandboxGuard).
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Verify that the API key is valid.
     */
    public function testKey(): TestKeyResponse
    {
        $this->logger->debug('testKey: invio richiesta');

        $raw      = $this->formPost(Routes::TEST_KEY, []);
        $response = $this->handler->parseTestKeyResponse($raw);

        if ($response->returnCode === 1) {
            $this->logger->info('testKey: chiave valida', [
                'account' => $response->emailOwner,
                'type'    => $response->subscriptionType,
            ]);
        } else {
            $this->logger->warning('testKey: chiave non valida', [
                'returnCode'  => $response->returnCode,
                'description' => $response->description,
            ]);
        }

        return $response;
    }

    /**
     * Create a document (fattura, ordine, preventivo...).
     *
     * Before generating XML, normalizeInvoice() is called to auto-populate
     * fields required by the SDI (e.g. feDestinationCode for FE documents).
     *
     * @param InvoiceData $invoice
     * @param string|null $idRequest Optional idempotency key, useful for deduplication.
     *                               Pass null (default) to omit — recommended during debug/testing.
     *
     * @return SaveDocumentResponse
     * @throws ValidationException
     * @throws Fattura24Exception
     */
    public function saveDocument(InvoiceData $invoice, ?string $idRequest = null): SaveDocumentResponse
    {
        $this->normalizeInvoice($invoice);

        $this->logger->debug('saveDocument: generazione XML', [
            'docType' => $invoice->document->documentType->value,
        ]);

        $xml = $this->xml->fromInvoice($invoice);

        if (XmlGenerator::hasErrors($xml)) {
            $errorMsg = XmlGenerator::getErrorMessage($xml);
            $this->logger->error('saveDocument: errore generazione XML', ['error' => $errorMsg]);

            throw new Fattura24Exception('XML generation error: ' . $errorMsg);
        }

        $data = ['xml' => $xml];

        if ($idRequest !== null) {
            $data['IdRequest'] = $idRequest;
        }

        $this->logger->debug('saveDocument: invio a Fattura24', ['idRequest' => $idRequest]);

        $raw      = $this->formPost(Routes::SAVE_DOCUMENT, $data);
        $response = $this->handler->parseDocumentResponse($raw);

        if ($response->isSuccess()) {
            $this->logger->info('saveDocument: documento creato', [
                'docId'     => $response->docId,
                'docNumber' => $response->docNumber,
                'docType'   => $response->docType,
                'duration'  => $raw['duration'] ?? null,
            ]);
        } else {
            $this->logger->warning('saveDocument: documento rifiutato da Fattura24', [
                'duration' => $raw['duration'] ?? null,
            ]);
        }

        return $response;
    }

    /**
     * Create or update a customer record.
     *
     * @throws ValidationException
     * @throws Fattura24Exception
     */
    public function saveCustomer(CustomerData $customer): array
    {
        $this->logger->debug('saveCustomer: generazione XML', [
            'customer' => $customer->customerName,
        ]);

        $xml = $this->xml->fromCustomer($customer);

        if (XmlGenerator::hasErrors($xml)) {
            $errorMsg = XmlGenerator::getErrorMessage($xml);
            $this->logger->error('saveCustomer: errore generazione XML', ['error' => $errorMsg]);

            throw new Fattura24Exception('XML generation error: ' . $errorMsg);
        }

        $raw = $this->formPost(Routes::SAVE_CUSTOMER, ['xml' => $xml]);

        $this->logger->info('saveCustomer: cliente salvato', ['customer' => $customer->customerName]);

        return $raw;
    }

    /**
     * Downloads a file (PDF/XML) from Fattura24.
     *
     * @param string $docId Document ID from saveDocument()
     * @return GetFileResponse Typed response with content, filename, metadata
     * @throws Fattura24Exception if API call fails
     */
    public function getFile(string $docId): GetFileResponse
    {
        $this->logger->debug('getFile: richiesta file', ['docId' => $docId]);

        $raw      = $this->formPost(Routes::GET_FILE, ['docId' => $docId], true);
        $response = GetFileResponse::fromHttpResponse($raw);

        $this->logger->debug('getFile: ricevuto', [
            'docId'    => $docId,
            'filename' => $response->filename,
        ]);

        return $response;
    }

    /**
     * Downloads a PDF and handles storage/display automatically.
     *
     * Behavior depends on configuration:
     * - If pdfDirectory set: saves to disk, returns filepath
     * - If headers not sent: streams to browser, returns null
     * - If headers sent: creates temp download link, returns array
     *
     * @param string $docId Document ID from saveDocument()
     * @param string|null $customFilename Override filename (optional)
     * @return string|array|null
     * @throws RuntimeException if download fails
     */
    public function downloadPdf(string $docId, ?string $customFilename = null): string|array|null
    {
        $file     = $this->getFile($docId);
        $filename = $customFilename ?? $file->filename;

        return $this->pdfManager->handle(
            $file->content,
            $filename,
            []
        );
    }

    /**
     * Get available document templates.
     *
     * @return GetTemplatesResponse Typed response with order and invoice templates
     */
    public function getTemplates(): GetTemplatesResponse
    {
        $this->logger->debug('getTemplates: richiesta');

        return $this->handler->parseTemplatesResponse(
            $this->formPost(Routes::GET_TEMPLATE, [])
        );
    }

    /**
     * Get available numerators (sezionali).
     *
     * @return GetNumeratorsResponse Typed response with numerators by document type
     */
    public function getNumerators(): GetNumeratorsResponse
    {
        $this->logger->debug('getNumerators: richiesta');

        return $this->handler->parseNumeratorsResponse(
            $this->formPost(Routes::GET_NUMERATOR, [])
        );
    }

    /**
     * Get the chart of accounts (Piano dei Conti).
     *
     * @return GetChartOfAccountsResponse Typed response with account ID => description map
     */
    public function getChartOfAccounts(): GetChartOfAccountsResponse
    {
        $this->logger->debug('getChartOfAccounts: richiesta');

        return $this->handler->parseChartOfAccountsResponse(
            $this->formPost(Routes::GET_PDC, [])
        );
    }

    // -------------------------------------------------------------------------
    // Advanced / escape-hatch
    // -------------------------------------------------------------------------

    /**
     * Send a raw form-urlencoded request, injecting auth automatically.
     * Use this for endpoints not yet covered by the SDK.
     */
    public function rawFormPost(string $url, array $data = [], bool $includeHeaders = false): array
    {
        return $this->formPost($url, $data, $includeHeaders);
    }

    /**
     * Send a multipart/form-data request, injecting auth automatically.
     * Useful for future file-upload endpoints.
     */
    public function rawMultipartPost(string $url, array $data = []): array
    {
        $data = $this->withAuth($data);

        return $this->http->post($url, $data, []);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Normalizes InvoiceData before XML generation.
     *
     * Currently handles FE documents only:
     * - Auto-populates feDestinationCode when both PEC and SDI are missing:
     *   '0000000' for Italian customers, 'XXXXXXX' for foreign customers.
     *
     * This is intentionally the only place where invoice data is mutated
     * before sending — keeping XmlGenerator read-only and stateless.
     */
    private function normalizeInvoice(InvoiceData $invoice): void
    {
        if ($invoice->document->documentType !== DocumentType::FatturaElettronica) {
            return;
        }

        $customer = $invoice->customer;
        $hasPec   = !empty($customer->feCustomerPec);
        $hasSdi   = !empty($customer->feDestinationCode);

        if (!$hasPec && !$hasSdi) {
            $isItalian = \strtoupper($customer->customerCountry ?? 'IT') === 'IT';
            $customer->feDestinationCode = $isItalian ? '0000000' : 'XXXXXXX';

            $this->logger->debug('normalizeInvoice: feDestinationCode auto-popolato', [
                'value'   => $customer->feDestinationCode,
                'country' => $customer->customerCountry,
            ]);
        }
    }

    /**
     * Perform a standard application/x-www-form-urlencoded POST.
     *
     * This is where apiKey and source are injected, and where http_build_query
     * is called. HttpClient receives a plain string body and explicit headers.
     */
    private function formPost(string $url, array $data, bool $includeHeaders = false): array
    {
        $body    = \http_build_query($this->withAuth($data));
        $headers = ['Content-Type: ' . HttpClient::CONTENT_TYPE_FORM];

        try {
            return $this->http->post($url, $body, $headers, $includeHeaders);
        } catch (Throwable $e) {
            $this->logger->error('Errore connessione HTTP', [
                'url'   => $url,
                'error' => $e->getMessage(),
                'code'  => $e->getCode(),
            ]);

            throw $e;
        }
    }

    /**
     * Merge apiKey and source into a data array.
     */
    private function withAuth(array $data): array
    {
        return \array_merge($data, [
            'apiKey' => $this->apiKey,
            'source' => $this->source,
        ]);
    }
}
