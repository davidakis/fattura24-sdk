<?php

namespace Davidakis\Fattura24SDK\Tests\Response;

use PHPUnit\Framework\TestCase;
use Davidakis\Fattura24SDK\Response\GetFileResponse;

class GetFileResponseTest extends TestCase
{
    private string $fakePdfContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakePdfContent = "%PDF-1.4\nFake PDF content\n%%EOF";
    }

    public function testFromHttpResponseExtractsFilename(): void
    {
        $httpResponse = [
            'body' => $this->fakePdfContent,
            'headers' => "HTTP/1.1 200 OK\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"invoice_12345.pdf\"\r\n",
        ];

        $response = GetFileResponse::fromHttpResponse($httpResponse);

        $this->assertEquals('invoice_12345.pdf', $response->filename);
        $this->assertEquals($this->fakePdfContent, $response->content);
        $this->assertEquals('application/pdf', $response->contentType);
        $this->assertEquals(strlen($this->fakePdfContent), $response->size);
    }

    public function testFromHttpResponseExtractsFilenameWithQuotes(): void
    {
        $httpResponse = [
            'body' => $this->fakePdfContent,
            'headers' => 'Content-Disposition: attachment; filename="test.pdf"',
        ];

        $response = GetFileResponse::fromHttpResponse($httpResponse);

        $this->assertEquals('test.pdf', $response->filename);
    }

    public function testFromHttpResponseExtractsFilenameWithoutQuotes(): void
    {
        $httpResponse = [
            'body' => $this->fakePdfContent,
            'headers' => 'Content-Disposition: attachment; filename=test.pdf',
        ];

        $response = GetFileResponse::fromHttpResponse($httpResponse);

        $this->assertEquals('test.pdf', $response->filename);
    }

    public function testFromHttpResponseGeneratesFallbackFilename(): void
    {
        $httpResponse = [
            'body' => $this->fakePdfContent,
            'headers' => 'Content-Type: application/pdf',
        ];

        $response = GetFileResponse::fromHttpResponse($httpResponse);

        $this->assertStringStartsWith('fattura24_', $response->filename);
        $this->assertStringEndsWith('.pdf', $response->filename);
    }

    public function testFromHttpResponseExtractsContentType(): void
    {
        $httpResponse = [
            'body' => $this->fakePdfContent,
            'headers' => "Content-Type: application/pdf\r\nContent-Disposition: attachment; filename=\"test.pdf\"",
        ];

        $response = GetFileResponse::fromHttpResponse($httpResponse);

        $this->assertEquals('application/pdf', $response->contentType);
    }

    public function testFromHttpResponseDefaultsContentType(): void
    {
        $httpResponse = [
            'body' => $this->fakePdfContent,
            'headers' => 'Content-Disposition: attachment; filename="test.pdf"',
        ];

        $response = GetFileResponse::fromHttpResponse($httpResponse);

        $this->assertEquals('application/octet-stream', $response->contentType);
    }

    public function testIsPdfReturnsTrueForPdfContentType(): void
    {
        $response = new GetFileResponse(
            content: $this->fakePdfContent,
            filename: 'test.pdf',
            contentType: 'application/pdf',
            size: strlen($this->fakePdfContent),
        );

        $this->assertTrue($response->isPdf());
    }

    public function testIsPdfReturnsTrueForPdfExtension(): void
    {
        $response = new GetFileResponse(
            content: $this->fakePdfContent,
            filename: 'test.pdf',
            contentType: 'application/octet-stream',
            size: strlen($this->fakePdfContent),
        );

        $this->assertTrue($response->isPdf());
    }

    public function testIsPdfReturnsFalseForNonPdf(): void
    {
        $response = new GetFileResponse(
            content: '<xml>data</xml>',
            filename: 'test.xml',
            contentType: 'application/xml',
            size: 15,
        );

        $this->assertFalse($response->isPdf());
    }

    public function testIsEmptyReturnsTrueForEmptyContent(): void
    {
        $response = new GetFileResponse(
            content: '',
            filename: 'empty.pdf',
            contentType: 'application/pdf',
            size: 0,
        );

        $this->assertTrue($response->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptyContent(): void
    {
        $response = new GetFileResponse(
            content: $this->fakePdfContent,
            filename: 'test.pdf',
            contentType: 'application/pdf',
            size: strlen($this->fakePdfContent),
        );

        $this->assertFalse($response->isEmpty());
    }

    public function testGetHumanSizeReturnsFormattedSize(): void
    {
        // 1500 bytes = 1.46 KB
        $response = new GetFileResponse(
            content: str_repeat('x', 1500),
            filename: 'test.pdf',
            contentType: 'application/pdf',
            size: 1500,
        );

        $humanSize = $response->getHumanSize();
        $this->assertStringContainsString('KB', $humanSize);
        $this->assertStringContainsString('1.', $humanSize);
    }

    public function testGetHumanSizeHandlesLargeFiles(): void
    {
        // 2 MB
        $response = new GetFileResponse(
            content: '',
            filename: 'large.pdf',
            contentType: 'application/pdf',
            size: 2 * 1024 * 1024,
        );

        $humanSize = $response->getHumanSize();
        $this->assertStringContainsString('MB', $humanSize);
        $this->assertStringContainsString('2', $humanSize);
    }

    public function testGetHumanSizeHandlesSmallFiles(): void
    {
        $response = new GetFileResponse(
            content: 'test',
            filename: 'tiny.pdf',
            contentType: 'application/pdf',
            size: 4,
        );

        $humanSize = $response->getHumanSize();
        $this->assertEquals('4 B', $humanSize);
    }
}
