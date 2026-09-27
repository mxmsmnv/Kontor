<?php

declare(strict_types=1);

namespace Kontor\API\Contracts;

/**
 * The outbound-HTTP boundary webhook delivery depends on, so
 * `WebhookDeliveryService` can be unit-tested with a fake implementation
 * instead of making real network calls — the same injectable-I/O-boundary
 * approach used for every other genuinely external dependency in this
 * monorepo (e.g. a `FakePdo` standing in for a live database connection).
 */
interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string}
     *
     * @throws \RuntimeException if the request could not be sent at all
     *                            (DNS failure, connection refused, timeout)
     */
    public function post(string $url, string $body, array $headers): array;
}
