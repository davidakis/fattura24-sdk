<?php

namespace SimplyIT\Fattura24SDK;

use InvalidArgumentException;
use RuntimeException;
use SimplyIT\Fattura24SDK\Api\HttpClient;
use SimplyIT\Fattura24SDK\Api\Routes;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\InvoiceData;
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;
use SimplyIT\Fattura24SDK\Exceptions\MissingApiKeyException;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;
use SimplyIT\Fattura24SDK\Handler\PdfManager;
use SimplyIT\Fattura24SDK\Handler\ResponseHandler;
use SimplyIT\Fattura24SDK\Response\GetChartOfAccountsResponse;
use SimplyIT\Fattura24SDK\Response\GetFileResponse;
use SimplyIT\Fattura24SDK\Response\GetNumeratorsResponse;
use SimplyIT\Fattura24SDK\Response\GetTemplatesResponse;
use SimplyIT\Fattura24SDK\Response\SaveDocumentResponse;
use SimplyIT\Fattura24SDK\Xml\XmlGenerator;

/**
 * Fattura24Client
 *
 * Main entry point for the SDK. Owns:
 *  - Authentication (apiKey, source injection into every request)
 *  - Request body encoding (http_build_query for form requests)
 *  - Orchestration of XmlGenerator → HttpClient → ResponseHandler
 *  - PDF download management
 *
 * HttpClient is kept completely agnostic: it receives a ready body
 * and an array of HTTP headers, nothing more.
 *
 * @version 2.0.0
 */
class Fattura24Client
{
    private string $apiKey;
    private string $source;
    private HttpClient $http;
    private XmlGenerator $xml;
    private ResponseHandler $handler;
    private PdfManager $pdfManager;

    /**
     * @param array $options
     *                       - apiKey  (string, required)
     *                       - source  (string, optional)  Your app name. Will be composed with the SDK
     *                       version identifier, e.g. "my-app SimplyIT-Fattura24SDK/2.0.0".
     *                       If omitted, only the SDK identifier is sent.
     *                       - timeout (int,    optional)  cURL timeout in seconds. Default: 60.
     *                       - pdfDir  (string, optional)  Directory to save downloaded PDFs. If null, PDFs output to browser.
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

        // Set PDF directory if provided
        if (!empty($options['pdfDir'])) {
            $this->pdfManager->setSaveDirectory($options['pdfDir']);
        }
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
     *
     * @return PdfManager
     */
    public function getPdfManager(): PdfManager
    {
        return $this->pdfManager;
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Verify that the API key is valid.
     */
    public function testKey(): array
    {
        return $this->formPost(Routes::TEST_KEY, []);
    }

    /**
     * Create a document (fattura, ordine, preventivo...).
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
        $xml = $this->xml->fromInvoice($invoice);

        if (XmlGenerator::hasErrors($xml)) {
            throw new Fattura24Exception(
                'XML generation error: ' . XmlGenerator::getErrorMessage($xml)
            );
        }

        $data = ['xml' => $xml];

        if ($idRequest !== null) {
            $data['IdRequest'] = $idRequest;
        }

        $raw = $this->formPost(Routes::SAVE_DOCUMENT, $data);

        // ResponseHandler directly creates typed response
        return $this->handler->parseDocumentResponse($raw);
    }

    /**
     * Create or update a customer record.
     *
     * @throws ValidationException
     * @throws Fattura24Exception
     */
    public function saveCustomer(CustomerData $customer): array
    {
        $xml = $this->xml->fromCustomer($customer);

        if (XmlGenerator::hasErrors($xml)) {
            throw new Fattura24Exception(
                'XML generation error: ' . XmlGenerator::getErrorMessage($xml)
            );
        }

        return $this->formPost(Routes::SAVE_CUSTOMER, ['xml' => $xml]);
    }

    /**
     * Download a document file (PDF, SDI XML...).
     *
     * @return array{filename: string, mime: string, content: string, raw: array}
     */
    /**
     * Downloads a file (PDF/XML) from Fattura24.
     *
     * @param string $docId Document ID from saveDocument()
     * @return GetFileResponse Typed response with content, filename, metadata
     * @throws Fattura24Exception if API call fails
     */
    public function getFile(string $docId): GetFileResponse
    {
        $raw = $this->formPost(Routes::GET_FILE, ['docId' => $docId], true);

        return GetFileResponse::fromHttpResponse($raw);
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
     *                           - string: Filepath if saved to disk
     *                           - array: Temp link info if headers already sent
     *                           - null: If streamed to browser (call exit() after this)
     * @throws RuntimeException if download fails
     */
    public function downloadPdf(string $docId, ?string $customFilename = null): string|array|null
    {
        $file = $this->getFile($docId);

        $filename = $customFilename ?? $file->filename;

        // Let PdfManager handle the file based on configuration
        return $this->pdfManager->handle(
            $file->content,
            $filename,
            [] // Headers already parsed in GetFileResponse
        );
    }

    /**
     * Get available document templates.
     *
     * @return array{order: array<int,string>, invoice: array<int,string>}
     */
    /**
     * Get available document templates.
     *
     * @return GetTemplatesResponse Typed response with order and invoice templates
     */
    public function getTemplates(): GetTemplatesResponse
    {
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
     *
     * @param string $url
     * @param array $data Additional payload (apiKey/source added automatically)
     * @param bool $includeHeaders
     */
    public function rawFormPost(string $url, array $data = [], bool $includeHeaders = false): array
    {
        return $this->formPost($url, $data, $includeHeaders);
    }

    /**
     * Send a multipart/form-data request, injecting auth automatically.
     * Useful for future file-upload endpoints.
     *
     * @param string $url
     * @param array $data Associative array — cURL handles multipart encoding
     */
    public function rawMultipartPost(string $url, array $data = []): array
    {
        $data = $this->withAuth($data);

        // For multipart, pass the raw array — cURL sets Content-Type + boundary automatically
        return $this->http->post(
            $url,
            $data,
            [] // no explicit Content-Type; cURL sets it when POSTFIELDS is an array
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

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

        return $this->http->post($url, $body, $headers, $includeHeaders);
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
