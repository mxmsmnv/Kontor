<?php

declare(strict_types=1);

namespace Kontor\Files\Infrastructure\Storage;

/**
 * HMAC-signed, time-limited download links (Substage 2.2 "signed URLs").
 * This produces and verifies the signature only — wiring an actual HTTP
 * endpoint that calls verify() and streams the file back is an admin-route
 * concern for a later stage, same as bin/kontor's restore commands not
 * being wired into a ProcessWire route yet.
 */
final class SignedUrlSigner
{
    public function __construct(
        private readonly string $secret,
        private readonly string $baseUrl,
    ) {
    }

    public function sign(string $path, \DateTimeImmutable $expiresAt): string
    {
        $expires = $expiresAt->getTimestamp();
        $signature = $this->computeSignature($path, $expires);

        $separator = str_contains($this->baseUrl, '?') ? '&' : '?';

        return $this->baseUrl . $separator . http_build_query([
            'path' => $path,
            'expires' => $expires,
            'signature' => $signature,
        ]);
    }

    public function verify(string $path, int $expires, string $signature): bool
    {
        if ($expires < time()) {
            return false;
        }

        return hash_equals($this->computeSignature($path, $expires), $signature);
    }

    private function computeSignature(string $path, int $expires): string
    {
        return hash_hmac('sha256', $path . '|' . $expires, $this->secret);
    }
}
