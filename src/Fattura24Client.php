<?php

namespace SimplyIT\Fattura24SDK;

use SimplyIT\Fattura24SDK\Api\HttpClient;
use SimplyIT\Fattura24SDK\Api\Routes;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\InvoiceData;
use SimplyIT\Fattura24SDK\Exceptions\Fattura24Exception;
use SimplyIT\Fattura24SDK\Exceptions\MissingApiKeyException;
use SimplyIT\Fattura24SDK\Exceptions\ValidationException;
use SimplyIT\Fattura24SDK\Handler\ResponseHandler;
use SimplyIT\Fattura24SDK\Version;
use SimplyIT\Fattura24SDK\Xml\XmlGenerator;

/**
 * Fattura24Client
 *
 * Main entry point for the SDK. Owns:
 *  - Authentication (apiKey, source injection into every request)
 *  - Request body encoding (http_build_query for form requests)
 *  - Orchestration of XmlGenerator → HttpClient → ResponseHandler
 *
 * HttpClient is kept completely agnostic: it receives a ready body
 * and an array of HTTP headers, nothing more.
 *
 * @version 1.0.0
 */
class Fattura24Client
{
    private string $apiKey;
    private string $source;
    private HttpClient $http;
    private XmlGenerator $xml;
    private ResponseHandler $handler;

    /**
     * @param array $options
     *   - apiKey  (string, required)
     *   - source  (string, optional)  Your app name. Will be composed with the SDK
     *                                 version identifier, e.g. "my-app SimplyIT-Fattura24SDK/1.0.0".
     *                                 If omitted, only the SDK identifier is sent.
     *   - timeout (int,    optional)  cURL timeout in seconds. Default: 60.
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

        $this->http    = $httpClient ?? new HttpClient($timeout);
        $this->xml     = new XmlGenerator();
        $this->handler = new ResponseHandler();
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
     * @param string|null $idRequest  Optional idempotency key, useful for deduplication.
     *                                Pass null (default) to omit — recommended during debug/testing.
     *
     * @throws ValidationException
     * @throws Fattura24Exception
     */
    public function saveDocument(InvoiceData $invoice, ?string $idRequest = null): array
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

        return [
            'docId'     => $this->handler->getDocId($raw),
            'docNumber' => $this->handler->getDocNumber($raw),
            'raw'       => $raw,
        ];
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
    public function getFile(string $docId): array
    {
        $raw = $this->formPost(Routes::GET_FILE, ['docId' => $docId], true);

        return [
            'filename' => HttpClient::extractFilename($raw['headers'] ?? ''),
            'mime'     => HttpClient::extractMimeType($raw['headers'] ?? ''),
            'content'  => $raw['body'] ?? '',
            'raw'      => $raw,
        ];
    }

    /**
     * Get available document templates.
     *
     * @return array{order: array<int,string>, invoice: array<int,string>}
     */
    public function getTemplates(): array
    {
        return $this->handler->parseTemplates(
            $this->formPost(Routes::GET_TEMPLATE, [])
        );
    }

    /**
     * Get available numerators (sezionali).
     *
     * @return array{invoice: array<int,string>, receipt: array<int,string>, electronic_invoice: array<int,string>}
     */
    public function getNumerators(): array
    {
        return $this->handler->parseNumerators(
            $this->formPost(Routes::GET_NUMERATOR, [])
        );
    }

    /**
     * Get the chart of accounts (Piano dei Conti).
     *
     * @return array<int, string>
     */
    public function getChartOfAccounts(): array
    {
        return $this->handler->parseChartOfAccounts(
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
     * @param array  $data           Additional payload (apiKey/source added automatically)
     * @param bool   $includeHeaders
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
     * @param array  $data  Associative array — cURL handles multipart encoding
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
        $body    = http_build_query($this->withAuth($data));
        $headers = ['Content-Type: ' . HttpClient::CONTENT_TYPE_FORM];

        return $this->http->post($url, $body, $headers, $includeHeaders);
    }

    /**
     * Merge apiKey and source into a data array.
     */
    private function withAuth(array $data): array
    {
        return array_merge($data, [
            'apiKey' => $this->apiKey,
            'source' => $this->source,
        ]);
    }
}
