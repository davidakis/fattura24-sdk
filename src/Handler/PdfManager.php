<?php

namespace SimplyIT\Fattura24SDK\Handler;

use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * PdfManager
 *
 * Handles PDF content from Fattura24 getFile() API.
 * Supports three modes:
 * 1. Save to disk (if saveDirectory configured)
 * 2. Stream to browser (if headers not sent)
 * 3. Temporary download link (fallback for headers_sent case)
 *
 * Design: Does NOT call exit() - application decides when to terminate.
 */
class PdfManager
{
    private ?string $saveDirectory = null;
    private ?Closure $urlGenerator = null;

    /**
     * Sets the directory where PDFs should be saved.
     *
     * @param string|null $path Absolute path to directory, or null to disable auto-save
     * @throws InvalidArgumentException if path doesn't exist or isn't writable
     */
    public function setSaveDirectory(?string $path): void
    {
        if ($path === null) {
            $this->saveDirectory = null;

            return;
        }

        $path = \rtrim($path, '/\\');

        if (!\is_dir($path)) {
            throw new InvalidArgumentException("Directory does not exist: {$path}");
        }

        if (!\is_writable($path)) {
            throw new InvalidArgumentException("Directory is not writable: {$path}");
        }

        $this->saveDirectory = $path;
    }

    /**
     * Returns the current save directory, or null if not set.
     */
    public function getSaveDirectory(): ?string
    {
        return $this->saveDirectory;
    }

    /**
     * Sets a custom URL generator for temporary download links.
     *
     * This is used when headers are already sent and the PDF can't be streamed
     * directly to the browser. The generator receives a unique ID and should
     * return a URL that your application will handle to serve the temp file.
     *
     * Example for WordPress:
     * ```php
     * $manager->setUrlGenerator(fn($id) => home_url("/download-pdf/{$id}"));
     * ```
     *
     * Example for Laravel:
     * ```php
     * $manager->setUrlGenerator(fn($id) => route('pdf.download', ['id' => $id]));
     * ```
     *
     * Example for Symfony:
     * ```php
     * $manager->setUrlGenerator(fn($id) => $this->router->generate('pdf_download', ['id' => $id]));
     * ```
     *
     * @param callable(string): string $generator Function that takes unique ID and returns URL
     */
    public function setUrlGenerator(callable $generator): void
    {
        $this->urlGenerator = $generator instanceof Closure
            ? $generator
            : Closure::fromCallable($generator);
    }

    /**
     * Handles PDF content from Fattura24 API response.
     *
     * Behavior priority:
     * 1. If saveDirectory set → save to disk, return filepath
     * 2. If headers not sent → stream to browser (readfile), return null
     * 3. If headers sent → create temp download link, return array
     *
     * @param string $content PDF binary content from getFile() API
     * @param string|null $filename Custom filename (optional, extracted from headers if null)
     * @param array $responseHeaders HTTP headers from API response (for filename extraction)
     * @return string|array|null
     *                           - string: Absolute filepath if saved to disk
     *                           - array: ['url' => ..., 'expires' => ..., 'path' => ...] if temp link created
     *                           - null: If streamed to browser (your app should call exit() after this)
     * @throws RuntimeException if save fails
     */
    public function handle(
        string $content,
        ?string $filename = null,
        array $responseHeaders = []
    ): string|array|null {
        // 1. Determine filename
        if ($filename === null) {
            $filename = $this->extractFilenameFromHeaders($responseHeaders);
        }
        $filename = $this->sanitizeFilename($filename);

        // 2. Save to directory (priority 1)
        if ($this->saveDirectory !== null) {
            return $this->saveToFile($content, $filename);
        }

        // 3. Stream to browser (priority 2)
        if (!\headers_sent()) {
            $this->streamToBrowser($content, $filename);

            return null;
        }

        // 4. Fallback: temporary download link (priority 3)
        return $this->createTempLink($content, $filename);
    }

    /**
     * Extracts filename from Content-Disposition header.
     *
     * Example header: Content-Disposition: attachment; filename="invoice_123.pdf"
     */
    private function extractFilenameFromHeaders(array $headers): string
    {
        foreach ($headers as $header) {
            if (\stripos($header, 'Content-Disposition:') === 0) {
                // Match: filename="invoice.pdf" or filename=invoice.pdf
                if (\preg_match('/filename[*]?=["\']?([^"\';\s]+)["\']?/i', $header, $matches)) {
                    return $matches[1];
                }
            }
        }

        // Fallback: generate timestamp-based name
        return 'fattura_' . \date('Ymd_His') . '.pdf';
    }

    /**
     * Sanitizes filename to prevent path traversal and invalid chars.
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove path components
        $filename = \basename($filename);

        // Remove dangerous characters
        $filename = \preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);

        // Ensure .pdf extension
        if (!\str_ends_with(\strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return $filename;
    }

    /**
     * Saves content to configured directory.
     *
     * @return string Absolute path to saved file
     * @throws RuntimeException if write fails
     */
    private function saveToFile(string $content, string $filename): string
    {
        $filepath = $this->saveDirectory . DIRECTORY_SEPARATOR . $filename;

        $bytes = \file_put_contents($filepath, $content);
        if ($bytes === false) {
            throw new RuntimeException("Failed to save PDF to {$filepath}");
        }

        return $filepath;
    }

    /**
     * Streams PDF content to browser using readfile().
     *
     * IMPORTANT: This method does NOT call exit().
     * Your application code must call exit() after this method if you don't
     * want any further output.
     *
     * Example usage:
     * ```php
     * $result = $pdfManager->handle($content, null, $headers);
     * if ($result === null) {
     *     // PDF was streamed to browser
     *     exit; // ← Your app decides when to exit
     * }
     * ```
     *
     * @param string $content PDF binary content
     * @param string $filename Sanitized filename
     */
    private function streamToBrowser(string $content, string $filename): void
    {
        // Create temporary file for readfile()
        $tempFile = \tempnam(\sys_get_temp_dir(), 'pdf_');
        \file_put_contents($tempFile, $content);

        // Send headers
        \header('Content-Type: application/pdf');
        \header('Content-Disposition: inline; filename="' . $filename . '"');
        \header('Content-Length: ' . \filesize($tempFile));
        \header('Cache-Control: private, max-age=0, must-revalidate');
        \header('Pragma: public');

        // Stream file (more memory efficient than echo for large files)
        \readfile($tempFile);

        // Cleanup temp file
        \unlink($tempFile);

        // Flush output buffer
        \flush();

        // NO exit() here - let the application decide
    }

    /**
     * Creates a temporary download link (fallback for headers_sent case).
     *
     * This is useful when headers are already sent (e.g., WordPress/Laravel
     * themes have already output HTML) and you can't stream directly to browser.
     *
     * The temp file expires after 1 hour and should be cleaned up by your
     * application (e.g., via cron job).
     *
     * @return array{url: string, expires: int, path: string, filename: string}
     */
    private function createTempLink(string $content, string $filename): array
    {
        // Create temp directory if needed
        $tempDir = \sys_get_temp_dir() . '/fattura24-temp';
        if (!\is_dir($tempDir)) {
            \mkdir($tempDir, 0o755, true);
        }

        // Generate unique filename
        $uniqueId = \uniqid('pdf_', true);
        $tempFile = $tempDir . '/' . $uniqueId . '.pdf';

        \file_put_contents($tempFile, $content);

        // Create download URL
        // Note: This requires your application to register a route that serves
        // files from the temp directory. Example:
        // Route: /fattura24-download/{uniqueId}
        // Implementation: readfile($tempDir . '/' . $uniqueId . '.pdf')
        $downloadUrl = $this->generateDownloadUrl($uniqueId);

        // Set expiration (1 hour)
        $expiresAt = \time() + 3600;

        return [
            'url' => $downloadUrl,
            'expires' => $expiresAt,
            'path' => $tempFile,
            'filename' => $filename,
        ];
    }

    /**
     * Generates download URL for temporary file.
     *
     * Uses custom URL generator if set via setUrlGenerator(), otherwise
     * returns a default path. Your application must register a route to
     * handle this URL and serve the file from sys_get_temp_dir()/fattura24-temp/.
     *
     * @param string $uniqueId Unique file identifier
     * @return string Download URL
     */
    protected function generateDownloadUrl(string $uniqueId): string
    {
        // Use custom generator if provided
        if ($this->urlGenerator !== null) {
            return ($this->urlGenerator)($uniqueId);
        }

        // Default fallback - requires manual route registration
        return '/fattura24-download/' . $uniqueId;
    }
}
