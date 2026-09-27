<?php

declare(strict_types=1);

namespace Kontor\API\Infrastructure\Http;

use Kontor\API\Contracts\HttpClientInterface;
use RuntimeException;

/**
 * The real `HttpClientInterface` implementation, used in production;
 * tests use a fake instead (see the interface's own doc comment) since
 * this sandbox has no outbound network access to verify against.
 */
final class CurlHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function post(string $url, string $body, array $headers): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException("Could not initialize an HTTP request to \"{$url}\".");
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $responseBody = curl_exec($handle);

        if ($responseBody === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new RuntimeException("HTTP request to \"{$url}\" failed: {$error}");
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return ['status' => $status, 'body' => (string) $responseBody];
    }
}
