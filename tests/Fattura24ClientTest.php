<?php

namespace SimplyIT\Fattura24SDK\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
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
     * Returns a PHPUnit mock of HttpClient that returns a canned response.
     * The constructor of HttpClient is never called — no cURL needed.
     *
     * @return MockObject&HttpClient
     */

    
    private function makeHttpMock(string $responseBody = ''): MockObject&HttpClient
    {
        if ($responseBody === '') {
            $responseBody = '<?xml version="1.0"?><root><docId>99</docId><docNumber>1/2025</docNumber></root>';
        }

        $mock = $this->createMock(HttpClient::class);
        $mock->method('post')->willReturn([
            'code'     => 200,
            'body'     => $responseBody,
            'duration' => 1.0,
        ]);

        return $mock;
    }

    
    private function makeClient(array $options = [], ?HttpClient $http = null): Fattura24Client
    {
        return new Fattura24Client(
            array_merge(['apiKey' => 'test-key'], $options),
            $http ?? $this->makeHttpMock()
        );
    }

    private function makeMinimalInvoice(): InvoiceData
    {
        $doc      = new DocumentData(DocumentType::FatturaElettronica, 1220.0, 1000.0, 220.0, false, 'MP05', 'Bonifico', 'IBAN');
        $customer = new CustomerData('Acme S.r.l.');
        $customer->setCustomerFiscalCode('NDLDVD75T26H501M');
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
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    return isset($params['source'])
                        && $params['source'] === Version::identifier();
                })
            )
            ->willReturn(['code' => 200, 'body' => '', 'duration' => 1.0]);

        $this->makeClient([], $http)->testKey();
    }

    public function testSourceWithAppNameComposesCorrectly(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    $source = $params['source'] ?? '';
                    return str_starts_with($source, 'mio-plugin')
                        && str_contains($source, Version::identifier());
                })
            )
            ->willReturn(['code' => 200, 'body' => '', 'duration' => 1.0]);

        $this->makeClient(['source' => 'mio-plugin'], $http)->testKey();
    }

    // -------------------------------------------------------------------------
    // saveDocument — IdRequest handling
    // -------------------------------------------------------------------------

    public function testSaveDocumentWithoutIdRequestOmitsField(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    return !array_key_exists('IdRequest', $params);
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $this->makeClient([], $http)->saveDocument($this->makeMinimalInvoice());
    }

    public function testSaveDocumentWithIdRequestIncludesField(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    return ($params['IdRequest'] ?? '') === 'REQ-2025-001';
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $this->makeClient([], $http)->saveDocument($this->makeMinimalInvoice(), 'REQ-2025-001');
    }

    public function testSaveDocumentWithNullIdRequestOmitsField(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    return !array_key_exists('IdRequest', $params);
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $this->makeClient([], $http)->saveDocument($this->makeMinimalInvoice(), null);
    }

    // -------------------------------------------------------------------------
    // saveDocument — response parsing
    // -------------------------------------------------------------------------

    /*public function testSaveDocumentReturnsDocIdAndDocNumber(): void
    {
           
        $client = $this->makeClient();
        $result = $client->saveDocument($this->makeMinimalInvoice());

        $this->assertSame('99',     $result->docId);
        $this->assertSame('1/2025', $result->docNumber);
        $this->assertNotNull($result->raw);
    }*/

    // -------------------------------------------------------------------------
    // Request structure
    // -------------------------------------------------------------------------

    public function testSaveDocumentPostsToCorrectUrl(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with($this->stringContains('SaveDocument'))
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $this->makeClient([], $http)->saveDocument($this->makeMinimalInvoice());
    }

    public function testSaveDocumentSendsFormContentTypeHeader(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(function (array $headers): bool {
                    return in_array('Content-Type: ' . HttpClient::CONTENT_TYPE_FORM, $headers, true);
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $this->makeClient([], $http)->saveDocument($this->makeMinimalInvoice());
    }

    public function testSaveDocumentBodyContainsApiKey(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    return ($params['apiKey'] ?? '') === 'my-secret-key';
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $this->makeClient(['apiKey' => 'my-secret-key'], $http)->saveDocument($this->makeMinimalInvoice());
    }

    public function testSaveDocumentBodyContainsXml(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    return isset($params['xml'])
                        && str_contains($params['xml'], '<Fattura24>');
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $this->makeClient([], $http)->saveDocument($this->makeMinimalInvoice());
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


    // -------------------------------------------------------------------------
    // normalizeInvoice — auto-popolamento feDestinationCode
    // -------------------------------------------------------------------------

    public function testNormalizeInvoiceSetsSdiZeroForItalianCustomerWithoutPecOrSdi(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    $xml = $params['xml'] ?? '';
                    return str_contains($xml, '<FeDestinationCode>0000000</FeDestinationCode>');
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $invoice = $this->makeFeInvoiceWithoutSdi('IT');
        $invoice->customer->setCustomerFiscalCode('NDLDVD75T26H501M'); // Codice fiscale italiano valido, per evitare che la normalizzazione lo consideri un cliente estero
        $this->makeClient([], $http)->saveDocument($invoice);
    }

    public function testNormalizeInvoiceSetsSdiXxxxxxxForForeignCustomerWithoutPecOrSdi(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    $xml = $params['xml'] ?? '';
                    return str_contains($xml, '<FeDestinationCode>XXXXXXX</FeDestinationCode>');
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $invoice = $this->makeFeInvoiceWithoutSdi('DE');
        $this->makeClient([], $http)->saveDocument($invoice);
    }

    public function testNormalizeInvoiceDoesNotOverwriteExistingSdi(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    $xml = $params['xml'] ?? '';
                    return str_contains($xml, '<FeDestinationCode>ABCDEFG</FeDestinationCode>');
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $invoice = $this->makeFeInvoiceWithoutSdi('IT');
        $invoice->customer->feDestinationCode = 'ABCDEFG';
        $invoice->customer->setCustomerFiscalCode('NDLDVD75t26h501M'); // Codice fiscale italiano valido, per evitare che la normalizzazione lo consideri un cliente estero
        $this->makeClient([], $http)->saveDocument($invoice);
    }

    public function testNormalizeInvoiceDoesNotOverwriteExistingPec(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    $xml = $params['xml'] ?? '';
                    // PEC presente — feDestinationCode NON deve essere auto-popolato
                    return !str_contains($xml, '<FeDestinationCode>0000000</FeDestinationCode>')
                        && !str_contains($xml, '<FeDestinationCode>XXXXXXX</FeDestinationCode>');
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        $invoice = $this->makeFeInvoiceWithoutSdi('IT');
        $invoice->customer->feCustomerPec = 'cliente@pec.it';
        $invoice->customer->setCustomerFiscalCode('NDLDVD75t26h501M'); // Codice fiscale italiano valido, per evitare che la normalizzazione lo consideri un cliente estero

        $this->makeClient([], $http)->saveDocument($invoice);
    }

    public function testNormalizeInvoiceSkippedForNonFeDocument(): void
    {
        $http = $this->makeHttpMock();
        $http->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(function (string $body): bool {
                    parse_str($body, $params);
                    $xml = $params['xml'] ?? '';
                    // Documento non FE — nessun SDI auto-popolato
                    return !str_contains($xml, '<FeDestinationCode>0000000</FeDestinationCode>')
                        && !str_contains($xml, '<FeDestinationCode>XXXXXXX</FeDestinationCode>');
                })
            )
            ->willReturn(['code' => 200, 'body' => '<?xml version="1.0"?><root><docId>1</docId><docNumber>1</docNumber></root>', 'duration' => 1.0]);

        // Documento tipo Fattura (non FE)
        $doc      = new \SimplyIT\Fattura24SDK\Data\DocumentData(
            DocumentType::Ricevuta, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', ''
        );
        $customer = new \SimplyIT\Fattura24SDK\Data\CustomerData('Test Srl');
        $row      = new \SimplyIT\Fattura24SDK\Data\RowData('Servizio', 1, 100.0, 22);
        $invoice  = new \SimplyIT\Fattura24SDK\Data\InvoiceData($doc, $customer, [$row]);

        $this->makeClient([], $http)->saveDocument($invoice);
    }

    // -------------------------------------------------------------------------
    // Helper privato per i test di normalizeInvoice
    // -------------------------------------------------------------------------

    private function makeFeInvoiceWithoutSdi(string $country = 'IT'): \SimplyIT\Fattura24SDK\Data\InvoiceData
    {
        $doc = new \SimplyIT\Fattura24SDK\Data\DocumentData(
            DocumentType::FatturaElettronica, 122.0, 100.0, 22.0, false, 'MP05', 'Bonifico', ''
        );
        $customer                  = new \SimplyIT\Fattura24SDK\Data\CustomerData('Acme Srl');
        $customer->customerCountry = $country;
        $customer->setCustomerFiscalCode('NDLDVD75T26H501M');
        // feCustomerPec e feDestinationCode deliberatamente non impostati
        $row     = new \SimplyIT\Fattura24SDK\Data\RowData('Servizio', 1, 100.0, 22);
        return new \SimplyIT\Fattura24SDK\Data\InvoiceData($doc, $customer, [$row]);
    }
}