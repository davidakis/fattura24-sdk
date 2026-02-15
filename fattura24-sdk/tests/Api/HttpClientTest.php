<?php

namespace SimplyIT\Fattura24SDK\Tests\Api;

use PHPUnit\Framework\TestCase;
use SimplyIT\Fattura24SDK\Api\HttpClient;

class HttpClientTest extends TestCase
{
    // -------------------------------------------------------------------------
    // extractFilename
    // -------------------------------------------------------------------------

    public function testExtractFilenameFromContentDispositionHeader(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Disposition: attachment; filename=\"fattura_12345.pdf\"\r\n";
        $this->assertSame('fattura_12345.pdf', HttpClient::extractFilename($headers));
    }

    public function testExtractFilenameReturnsEmptyWhenHeaderMissing(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Type: application/json\r\n";
        $this->assertSame('', HttpClient::extractFilename($headers));
    }

    public function testExtractFilenameReturnsEmptyForEmptyString(): void
    {
        $this->assertSame('', HttpClient::extractFilename(''));
    }

    public function testExtractFilenameIsCaseInsensitiveOnHeaderName(): void
    {
        $headers = "HTTP/1.1 200 OK\r\ncontent-disposition: attachment; filename=\"test.pdf\"\r\n";
        $this->assertSame('test.pdf', HttpClient::extractFilename($headers));
    }

    public function testExtractFilenameHandlesXmlFile(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Disposition: attachment; filename=\"FE_12345.xml\"\r\n";
        $this->assertSame('FE_12345.xml', HttpClient::extractFilename($headers));
    }

    // -------------------------------------------------------------------------
    // extractMimeType
    // -------------------------------------------------------------------------

    public function testExtractMimeTypeForPdf(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Type: application/pdf\r\n";
        $this->assertSame('application/pdf', HttpClient::extractMimeType($headers));
    }

    public function testExtractMimeTypeForXml(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Type: application/xml\r\n";
        $this->assertSame('application/xml', HttpClient::extractMimeType($headers));
    }

    public function testExtractMimeTypeReturnsEmptyWhenHeaderMissing(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Disposition: attachment; filename=\"test.pdf\"\r\n";
        $this->assertSame('', HttpClient::extractMimeType($headers));
    }

    public function testExtractMimeTypeReturnsEmptyForEmptyString(): void
    {
        $this->assertSame('', HttpClient::extractMimeType(''));
    }

    public function testExtractMimeTypeIsCaseInsensitiveOnHeaderName(): void
    {
        $headers = "HTTP/1.1 200 OK\r\ncontent-type: application/pdf\r\n";
        $this->assertSame('application/pdf', HttpClient::extractMimeType($headers));
    }

    public function testExtractMimeTypeWithCharsetParam(): void
    {
        $headers = "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\n";
        $this->assertStringContainsString('text/xml', HttpClient::extractMimeType($headers));
    }

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public function testContentTypeConstants(): void
    {
        $this->assertSame('application/x-www-form-urlencoded', HttpClient::CONTENT_TYPE_FORM);
        $this->assertSame('multipart/form-data',               HttpClient::CONTENT_TYPE_MULTIPART);
        $this->assertSame('application/json',                  HttpClient::CONTENT_TYPE_JSON);
    }
}
