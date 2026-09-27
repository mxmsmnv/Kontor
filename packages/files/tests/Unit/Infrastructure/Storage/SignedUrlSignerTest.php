<?php

declare(strict_types=1);

namespace Kontor\Files\Tests\Unit\Infrastructure\Storage;

use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use PHPUnit\Framework\TestCase;

final class SignedUrlSignerTest extends TestCase
{
    public function test_a_freshly_signed_url_verifies(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.test/download');
        $expiresAt = (new \DateTimeImmutable())->modify('+1 hour');

        $url = $signer->sign('org-1/file.pdf', $expiresAt);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->assertTrue($signer->verify($params['path'], (int) $params['expires'], $params['signature']));
    }

    public function test_an_expired_url_does_not_verify(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.test/download');
        $expired = (new \DateTimeImmutable())->modify('-1 hour');

        $url = $signer->sign('org-1/file.pdf', $expired);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->assertFalse($signer->verify($params['path'], (int) $params['expires'], $params['signature']));
    }

    public function test_a_tampered_path_does_not_verify(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.test/download');
        $expiresAt = (new \DateTimeImmutable())->modify('+1 hour');

        $url = $signer->sign('org-1/file.pdf', $expiresAt);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->assertFalse($signer->verify('org-1/other-file.pdf', (int) $params['expires'], $params['signature']));
    }

    public function test_a_different_secret_does_not_verify(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.test/download');
        $otherSigner = new SignedUrlSigner('different-secret', 'https://example.test/download');
        $expiresAt = (new \DateTimeImmutable())->modify('+1 hour');

        $url = $signer->sign('org-1/file.pdf', $expiresAt);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        $this->assertFalse($otherSigner->verify($params['path'], (int) $params['expires'], $params['signature']));
    }

    public function test_sign_appends_query_correctly_when_base_url_already_has_a_query_string(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.test/download?foo=bar');
        $url = $signer->sign('org-1/file.pdf', (new \DateTimeImmutable())->modify('+1 hour'));

        $this->assertStringContainsString('foo=bar&path=', $url);
    }
}
