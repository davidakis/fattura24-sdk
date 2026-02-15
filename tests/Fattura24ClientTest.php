<?php

namespace SimplyIT\Fattura24SDK\Tests;

use PHPUnit\Framework\TestCase;
use SimplyIT\Fattura24SDK\Api\HttpClient;
use SimplyIT\Fattura24SDK\Data\CustomerData;
use SimplyIT\Fattura24SDK\Data\DocumentData;
use SimplyIT\Fattura24SDK\Data\DocumentType;
use SimplyIT\Fattura24SDK\Data\InvoiceData;
use SimplyIT\Fattura24SDK\Data\RowData;
use SimplyIT\Fattura24SDK\Exceptions\MissingApiKeyException;
use SimplyIT\Fattura24SDK\Fattura24Client;
use SimplyIT\Fattura24SDK\Version;

class Fattura24ClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns a stub HttpClient that captures the last call and returns a canned response.
     */
    private function makeStubHttp(array $responseBody = []): object
    {
        $defaultBody = '<?xml version="1.0"?><root><docId>99</docId><docNumber>1/2025</docNumber></root>';

        return new class($responseBody['body'] ?? $defaultBody) extends HttpClient {
            public array $lastCall = [];

            private string $cannedBody;

            public function __construct(string $body)
            {
                // do NOT call parent — no cURL needed in tests
                $this->cannedBody = $body;
            }

            public function post(string $url, $body, array $headers = [], bool $includeHeaders = false): array
            {
                $this->lastCall = compact('url', 'body', 'headers', 'includeHeaders');
                return ['code' => 200, 'body' => $this->cannedBody, 'duration' => 1.0];
            }
        };
    }

    private function makeClient(array $options = [], object $stub = null): Fattura24Client
    {
        $options = array_merge(['apiKey' => 'test-key'], $options);
        return new Fattura24Client($options, $stub ?? $this->makeStubHttp());
    }

    private function makeMinimalInvoice(): InvoiceData
    {
        $doc      = new DocumentData(DocumentType::FatturaElettronica, 1220.0, 1000.0, 220.0, false, 'MP05', 'Bonifico', 'IBAN');
        $customer = new CustomerData('Acme S.r.l.');
        $row      = new RowData('Visita', 1, 1000.0, 22);
        return new InvoiceData($doc, $customer, [$row]);
    }

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testMissingApiKeyThrowsException(): void
    {
        $this->expectException(MissingApiKeyException::class);
        new Fattura24Client([]);
    }

    public function testEmptyApiKeyThrowsException(): void
    {
        $this->expectException(MissingApiKeyException::class);
        new Fattura24Client(['apiKey' => '']);
    }

    // -------------------------------------------------------------------------
    // Source composition
    // -------------------------------------------------------------------------

    public function testSourceWithoutAppNameIsJustSdkIdentifier(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient([], $stub);
        $client->testKey();

        $body = $stub->lastCall['body'];
        $this->assertStringContainsString(Version::identifier(), $body);
        $this->assertStringContainsString('source=', $body);
    }

    public function testSourceWithAppNameComposesCorrectly(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient(['source' => 'mio-plugin'], $stub);
        $client->testKey();

        $body = $stub->lastCall['body'];
        parse_str($body, $params);

        $this->assertStringContainsString('mio-plugin',         $params['source']);
        $this->assertStringContainsString(Version::identifier(), $params['source']);
        // app name comes first
        $this->assertStringStartsWith('mio-plugin', $params['source']);
    }

    // -------------------------------------------------------------------------
    // saveDocument — IdRequest handling
    // -------------------------------------------------------------------------

    public function testSaveDocumentWithoutIdRequestOmitsField(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient([], $stub);
        $client->saveDocument($this->makeMinimalInvoice());

        parse_str($stub->lastCall['body'], $params);
        $this->assertArrayNotHasKey('IdRequest', $params);
    }

    public function testSaveDocumentWithIdRequestIncludesField(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient([], $stub);
        $client->saveDocument($this->makeMinimalInvoice(), 'REQ-2025-001');

        parse_str($stub->lastCall['body'], $params);
        $this->assertArrayHasKey('IdRequest', $params);
        $this->assertSame('REQ-2025-001', $params['IdRequest']);
    }

    public function testSaveDocumentWithNullIdRequestOmitsField(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient([], $stub);
        $client->saveDocument($this->makeMinimalInvoice(), null);

        parse_str($stub->lastCall['body'], $params);
        $this->assertArrayNotHasKey('IdRequest', $params);
    }

    // -------------------------------------------------------------------------
    // saveDocument — response parsing
    // -------------------------------------------------------------------------

    public function testSaveDocumentReturnsDocIdAndDocNumber(): void
    {
        $client = $this->makeClient();
        $result = $client->saveDocument($this->makeMinimalInvoice());

        $this->assertSame('99',     $result['docId']);
        $this->assertSame('1/2025', $result['docNumber']);
        $this->assertArrayHasKey('raw', $result);
    }

    // -------------------------------------------------------------------------
    // Request structure
    // -------------------------------------------------------------------------

    public function testSaveDocumentPostsToCorrectUrl(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient([], $stub);
        $client->saveDocument($this->makeMinimalInvoice());

        $this->assertStringContainsString('SaveDocument', $stub->lastCall['url']);
    }

    public function testSaveDocumentSendsFormContentTypeHeader(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient([], $stub);
        $client->saveDocument($this->makeMinimalInvoice());

        $headers = $stub->lastCall['headers'];
        $this->assertContains(
            'Content-Type: ' . HttpClient::CONTENT_TYPE_FORM,
            $headers
        );
    }

    public function testSaveDocumentBodyContainsApiKey(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient(['apiKey' => 'my-secret-key'], $stub);
        $client->saveDocument($this->makeMinimalInvoice());

        parse_str($stub->lastCall['body'], $params);
        $this->assertSame('my-secret-key', $params['apiKey']);
    }

    public function testSaveDocumentBodyContainsXml(): void
    {
        $stub   = $this->makeStubHttp();
        $client = $this->makeClient([], $stub);
        $client->saveDocument($this->makeMinimalInvoice());

        parse_str($stub->lastCall['body'], $params);
        $this->assertArrayHasKey('xml', $params);
        $this->assertStringContainsString('<Fattura24>', $params['xml']);
    }

    // -------------------------------------------------------------------------
    // Version
    // -------------------------------------------------------------------------

    public function testVersionIdentifierHasCorrectFormat(): void
    {
        $this->assertMatchesRegularExpression(
            '/^SimplyIT-Fattura24SDK-\d+\.\d+\.\d+$/',
            Version::identifier()
        );
    }
}
