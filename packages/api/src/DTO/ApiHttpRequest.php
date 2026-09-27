<?php

declare(strict_types=1);

namespace Kontor\API\DTO;

/**
 * An explicit, plain-data stand-in for a real HTTP request — the module's
 * ProcessWire hook glue builds one of these from the live request and
 * hands it to `ApiRequestHandler`, which never touches superglobals or
 * `$this->wire()` itself and is therefore fully unit-testable.
 */
final class ApiHttpRequest
{
    /**
     * @param array<string, mixed> $queryParams already-decoded (e.g. via parse_str())
     * @param array<string, string> $headers lower-cased header names
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $queryParams,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
