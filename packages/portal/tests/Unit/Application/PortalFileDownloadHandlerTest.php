<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Unit\Application;

use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Portal\Application\PortalFileDownloadHandler;
use Kontor\SDK\Contracts\StorageInterface;
use Kontor\SDK\DTO\StoredFile;
use PHPUnit\Framework\TestCase;

final class PortalFileDownloadHandlerTest extends TestCase
{
    private function storage(bool $exists = true): StorageInterface
    {
        return new class($exists) implements StorageInterface {
            public function __construct(private readonly bool $exists)
            {
            }

            public function put(string $path, mixed $contents, array $options = []): StoredFile
            {
                throw new \RuntimeException('not used in this test');
            }

            public function read(string $path)
            {
                return fopen('php://memory', 'r');
            }

            public function delete(string $path): void
            {
            }

            public function exists(string $path): bool
            {
                return $this->exists;
            }

            public function temporaryUrl(string $path, \DateTimeImmutable $expiresAt): string
            {
                return '';
            }
        };
    }

    public function test_a_validly_signed_request_for_an_existing_file_is_served(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.com/portal/files/download');
        $expiresAt = new \DateTimeImmutable('+10 minutes');
        $signedUrl = $signer->sign('org1/invoice1/file.pdf', $expiresAt);
        parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $query);

        $handler = new PortalFileDownloadHandler($signer, $this->storage());
        $result = $handler->handle($query['path'], (int) $query['expires'], $query['signature']);

        $this->assertSame(200, $result->status);
        $this->assertSame('application/pdf', $result->mimeType);
        $this->assertNotNull($result->stream);
    }

    public function test_a_tampered_signature_is_forbidden(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.com/portal/files/download');
        $expiresAt = new \DateTimeImmutable('+10 minutes');
        $signedUrl = $signer->sign('org1/invoice1/file.pdf', $expiresAt);
        parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $query);

        $handler = new PortalFileDownloadHandler($signer, $this->storage());
        $result = $handler->handle($query['path'], (int) $query['expires'], 'not-the-real-signature');

        $this->assertSame(403, $result->status);
        $this->assertNull($result->stream);
    }

    public function test_an_expired_signature_is_forbidden(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.com/portal/files/download');
        $expiresAt = new \DateTimeImmutable('-10 minutes');
        $signedUrl = $signer->sign('org1/invoice1/file.pdf', $expiresAt);
        parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $query);

        $handler = new PortalFileDownloadHandler($signer, $this->storage());
        $result = $handler->handle($query['path'], (int) $query['expires'], $query['signature']);

        $this->assertSame(403, $result->status);
    }

    public function test_a_valid_signature_for_a_missing_file_is_404(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.com/portal/files/download');
        $expiresAt = new \DateTimeImmutable('+10 minutes');
        $signedUrl = $signer->sign('org1/invoice1/file.pdf', $expiresAt);
        parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $query);

        $handler = new PortalFileDownloadHandler($signer, $this->storage(exists: false));
        $result = $handler->handle($query['path'], (int) $query['expires'], $query['signature']);

        $this->assertSame(404, $result->status);
    }

    public function test_mime_type_is_guessed_from_the_extension(): void
    {
        $signer = new SignedUrlSigner('secret', 'https://example.com/portal/files/download');
        $expiresAt = new \DateTimeImmutable('+10 minutes');
        $signedUrl = $signer->sign('org1/invoice1/photo.PNG', $expiresAt);
        parse_str((string) parse_url($signedUrl, PHP_URL_QUERY), $query);

        $handler = new PortalFileDownloadHandler($signer, $this->storage());
        $result = $handler->handle($query['path'], (int) $query['expires'], $query['signature']);

        $this->assertSame('image/png', $result->mimeType);
    }
}
