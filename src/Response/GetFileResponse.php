<?php

namespace SimplyIT\Fattura24SDK\Response;

/**
 * GetFileResponse
 *
 * Represents a file (PDF/XML) downloaded from Fattura24 API.
 * Encapsulates content, metadata, and header parsing.
 */
final readonly class GetFileResponse
{
    public function __construct(
        public string $content,
        public string $filename,
        public string $contentType,
        public int $size,
    ) {
    }

    /**
     * Creates response from raw HTTP response.
     *
     * @param array $httpResponse Response from HttpClient with 'body' and 'headers'
     * @return self
     */
    public static function fromHttpResponse(array $httpResponse): self
    {
        $content = $httpResponse['body'] ?? '';
        $headers = $httpResponse['headers'] ?? '';

        $filename = self::extractFilenameFromHeaders($headers);
        $contentType = self::extractContentType($headers);

        return new self(
            content: $content,
            filename: $filename,
            contentType: $contentType,
            size: \strlen($content),
        );
    }

    /**
     * Extracts filename from Content-Disposition header.
     *
     * Example: Content-Disposition: attachment; filename="invoice_123.pdf"
     *
     * @param string $headers Raw HTTP headers
     * @return string Filename or generated fallback
     */
    private static function extractFilenameFromHeaders(string $headers): string
    {
        $headerLines = \explode("\r\n", $headers);

        foreach ($headerLines as $line) {
            if (\stripos($line, 'Content-Disposition:') === 0) {
                // Match: filename="invoice.pdf" or filename=invoice.pdf
                if (\preg_match('/filename[*]?=["\']?([^"\';\s]+)["\']?/i', $line, $matches)) {
                    return $matches[1];
                }
            }
        }

        // Fallback: generate timestamp-based name
        return 'fattura24_' . \date('Ymd_His') . '.pdf';
    }

    /**
     * Extracts Content-Type from headers.
     *
     * @param string $headers Raw HTTP headers
     * @return string Content-Type or 'application/octet-stream' as fallback
     */
    private static function extractContentType(string $headers): string
    {
        $headerLines = \explode("\r\n", $headers);

        foreach ($headerLines as $line) {
            if (\stripos($line, 'Content-Type:') === 0) {
                return \trim(\substr($line, \strlen('Content-Type:')));
            }
        }

        return 'application/octet-stream';
    }

    /**
     * Checks if this is a PDF file.
     */
    public function isPdf(): bool
    {
        return $this->contentType === 'application/pdf'
            || \str_ends_with(\strtolower($this->filename), '.pdf');
    }

    /**
     * Checks if content is empty.
     */
    public function isEmpty(): bool
    {
        return $this->size === 0;
    }

    /**
     * Returns human-readable file size.
     *
     * @return string e.g., "1.5 MB"
     */
    public function getHumanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < \count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return \round($size, 2) . ' ' . $units[$unit];
    }
}
