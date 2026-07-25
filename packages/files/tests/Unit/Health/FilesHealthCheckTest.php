<?php

declare(strict_types=1);

namespace Kontor\Files\Tests\Unit\Health;

use Kontor\Files\Health\FilesHealthCheck;
use Kontor\Files\Infrastructure\Storage\LocalPrivateStorage;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\SDK\Contracts\StorageInterface;
use PHPUnit\Framework\TestCase;

final class FilesHealthCheckTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kontor-files-health-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->root)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }

            rmdir($this->root);
        }
    }

    public function test_ok_when_storage_is_writable(): void
    {
        $storage = new LocalPrivateStorage($this->root, new SignedUrlSigner('secret', 'https://example.test'));

        $result = (new FilesHealthCheck($storage))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_critical_when_storage_put_throws(): void
    {
        $storage = new class implements StorageInterface {
            public function put(string $path, mixed $contents, array $options = []): \Kontor\SDK\DTO\StoredFile
            {
                throw new \RuntimeException('disk full');
            }

            public function read(string $path)
            {
                throw new \RuntimeException('not implemented');
            }

            public function delete(string $path): void
            {
            }

            public function exists(string $path): bool
            {
                return false;
            }

            public function temporaryUrl(string $path, \DateTimeImmutable $expiresAt): string
            {
                return '';
            }
        };

        $result = (new FilesHealthCheck($storage))->run();

        $this->assertSame('critical', $result->status);
        $this->assertStringContainsString('disk full', $result->message);
    }
}
