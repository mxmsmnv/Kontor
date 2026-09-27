<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Infrastructure\Http;

use Kontor\Marketplace\Contracts\RegistryClientInterface;
use RuntimeException;

/**
 * The real `RegistryClientInterface` implementation, used in production;
 * tests use a fake instead (see the interface's own doc comment) since
 * this sandbox has no outbound network access to verify against.
 */
final class CurlRegistryClient implements RegistryClientInterface
{
    public function __construct(
        private readonly int $timeoutSeconds = 10,
    ) {
    }

    public function fetch(string $url): string
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException("Could not initialize an HTTP request to \"{$url}\".");
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $body = curl_exec($handle);

        if ($body === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new RuntimeException("Fetching \"{$url}\" failed: {$error}");
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException("Fetching \"{$url}\" returned HTTP {$status}.");
        }

        return (string) $body;
    }
}
