<?php

namespace Davidakis\Fattura24SDK\Api;

use Davidakis\Fattura24SDK\Exceptions\ConnectionException;
use Davidakis\Fattura24SDK\Exceptions\CurlNotInstalledException;
use Davidakis\Fattura24SDK\Version;

/**
 * HttpClient
 *
 * Low-level cURL wrapper. Completely agnostic about:
 *  - how the request body is encoded (the caller decides)
 *  - what Content-Type is used
 *  - what the body contains
 *
 * The caller (Fattura24Client) is responsible for building the body
 * and setting appropriate headers before calling post().
 */
class HttpClient
{
    public const CONTENT_TYPE_FORM    = 'application/x-www-form-urlencoded';
    public const CONTENT_TYPE_MULTIPART = 'multipart/form-data';
    public const CONTENT_TYPE_JSON    = 'application/json';

    private int $timeout = 60;
    private int $maxRetries = 3;
    private float $retryDelay = 1.0; // seconds

    public function __construct(int $timeout = 60)
    {
        $this->timeout = $timeout;
    }

    /**
     * Sets the maximum number of retry attempts for failed requests.
     * Default: 3
     *
     * @param int $maxRetries Number of retries (0 = no retries)
     */
    public function setMaxRetries(int $maxRetries): void
    {
        $this->maxRetries = \max(0, $maxRetries);
    }

    /**
     * Sets the initial delay between retries in seconds.
     * Subsequent retries use exponential backoff.
     * Default: 1.0
     *
     * @param float $seconds Initial delay in seconds
     */
    public function setRetryDelay(float $seconds): void
    {
        $this->retryDelay = \max(0.1, $seconds);
    }

    /**
     * Perform a POST request.
     *
     * @param string $url Endpoint URL
     * @param string|array $body Pre-built request body.
     *                           Pass a string for urlencoded / JSON.
     *                           Pass an array for multipart (cURL handles encoding).
     * @param string[] $headers HTTP headers as ['Header-Name: value', ...]
     * @param bool $includeHeaders Whether to return response headers
     *
     * @return array{code: int, body: string, duration: float, headers?: string}
     *
     * @throws CurlNotInstalledException
     * @throws ConnectionException
     */
    public function post(
        string $url,
        $body,
        array $headers = [],
        bool $includeHeaders = false
    ): array {
        return $this->executeWithRetry(
            fn () => $this->doPost($url, $body, $headers, $includeHeaders)
        );
    }

    /**
     * Executes a request with automatic retry on transient failures.
     *
     * @template T
     * @param callable(): T $request
     * @return T
     * @throws ConnectionException if all retries exhausted
     */
    private function executeWithRetry(callable $request)
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                return $request();
            } catch (ConnectionException $e) {
                $lastException = $e;
                $attempt++;

                if ($e->getCode() >= 400 && $e->getCode() < 600) {
                    throw $e;
                }

                if ($attempt > $this->maxRetries) {
                    throw $e;
                }

                $delay = $this->retryDelay * (2 ** ($attempt - 1));
                \usleep((int) ($delay * 1000000));
            }
        }

        // Unreachable in practice, but satisfies static analysis
        throw $lastException ?? new ConnectionException('Max retries exhausted.');
    }

    /**
     * Actual POST implementation (called by post() via retry wrapper).
     *
     * @param string $url Endpoint URL
     * @param string|array $body Pre-built request body
     * @param string[] $headers HTTP headers
     * @param bool $includeHeaders Whether to return response headers
     *
     * @return array{code: int, body: string, duration: float, headers?: string}
     *
     * @throws CurlNotInstalledException
     * @throws ConnectionException
     */
    private function doPost(
        string $url,
        $body,
        array $headers = [],
        bool $includeHeaders = false
    ): array {
        if (!\function_exists('curl_init')) {
            throw new CurlNotInstalledException(
                'The cURL PHP extension is required but not installed.'
            );
        }

        $start = \microtime(true);

        $ch = \curl_init();
        \curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_USERAGENT      => Version::identifier(),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => $includeHeaders ? 1 : 0,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $output       = \curl_exec($ch);
        $responseCode = (int) \curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlErrno    = \curl_errno($ch);
        $curlError    = \curl_error($ch);
        $duration     = \round((\microtime(true) - $start) * 1000, 2);

        if ($curlErrno !== 0) {
            \curl_close($ch);

            throw new ConnectionException(
                \sprintf('cURL error %d: %s', $curlErrno, $curlError)
            );
        }

        if ($responseCode !== 200) {
            \curl_close($ch);

            throw new ConnectionException(
                \sprintf('HTTP %d calling %s', $responseCode, $url),
                $responseCode
            );
        }

        $result = [
            'code'     => $responseCode,
            'body'     => $output,
            'duration' => $duration,
        ];

        if ($includeHeaders) {
            $headerSize        = \curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $result['headers'] = \substr((string) $output, 0, $headerSize);
            $result['body']    = \substr((string) $output, $headerSize);
        }

        \curl_close($ch);

        return $result;
    }

    /**
     * Extract filename from a Content-Disposition header string.
     */
    public static function extractFilename(string $headers): string
    {
        foreach (\explode("\r\n", $headers) as $line) {
            if (\stripos($line, 'Content-Disposition:') !== false) {
                if (\preg_match('/filename="([^"]+)"/', $line, $matches)) {
                    return $matches[1];
                }
            }
        }

        return '';
    }

    /**
     * Extract MIME type from a Content-Type header string.
     */
    public static function extractMimeType(string $headers): string
    {
        foreach (\explode("\r\n", $headers) as $line) {
            if (\stripos($line, 'Content-Type:') !== false) {
                return \trim(\substr($line, \strlen('Content-Type:')));
            }
        }

        return '';
    }
}
