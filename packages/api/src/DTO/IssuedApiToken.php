<?php

declare(strict_types=1);

namespace Kontor\API\DTO;

use Kontor\API\Domain\ApiToken;

/**
 * Returned exactly once, at creation — `$plaintext` is never stored or
 * recoverable afterwards (only its hash is, on `$token`).
 */
final class IssuedApiToken
{
    public function __construct(
        public readonly ApiToken $token,
        public readonly string $plaintext,
    ) {
    }
}
