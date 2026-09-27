<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Contracts;

/**
 * The outbound-HTTP boundary registry syncing depends on, so
 * `RegistrySyncService` is unit-testable with a fake registry payload
 * instead of a live network call — the same injectable-I/O-boundary
 * idea `kontor/api`'s own `HttpClientInterface` uses for webhook
 * delivery (a distinct interface, not reused directly, since this one is
 * a GET fetch rather than a signed POST delivery).
 */
interface RegistryClientInterface
{
    /**
     * @throws \RuntimeException if the request could not be completed at all
     */
    public function fetch(string $url): string;
}
