<?php

declare(strict_types=1);

namespace Kontor\Files\Tests\Unit\Infrastructure\Storage;

use Kontor\Files\Infrastructure\Storage\LocalPrivateStorage;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalPrivateStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kontor-files-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    private function storage(): LocalPrivateStorage
    {
        return new LocalPrivateStorage($this->root, new SignedUrlSigner('secret', 'https://example.test/download'));
    }

    public function test_put_writes_the_file_and_returns_correct_metadata(): void
    {
        $stored = $this->storage()->put('org-1/report.csv', "a,b\n1,2");

        $this->assertSame('org-1/report.csv', $stored->path);
        $this->assertSame('local', $stored->storage);
        $this->assertSame(strlen("a,b\n1,2"), $stored->sizeBytes);
        $this->assertSame(hash('sha256', "a,b\n1,2"), $stored->checksum);
    }

    public function test_exists_and_read_round_trip(): void
    {
        $storage = $this->storage();
        $storage->put('org-1/notes.txt', 'hello');

        $this->assertTrue($storage->exists('org-1/notes.txt'));
        $handle = $storage->read('org-1/notes.txt');
        $this->assertSame('hello', stream_get_contents($handle));
        fclose($handle);
    }

    public function test_delete_removes_the_file(): void
    {
        $storage = $this->storage();
        $storage->put('org-1/notes.txt', 'hello');

        $storage->delete('org-1/notes.txt');

        $this->assertFalse($storage->exists('org-1/notes.txt'));
    }

    public function test_read_throws_for_a_missing_file(): void
    {
        $this->expectException(RuntimeException::class);

        $this->storage()->read('org-1/missing.txt');
    }

    public function test_temporary_url_delegates_to_the_signer(): void
    {
        $url = $this->storage()->temporaryUrl('org-1/notes.txt', (new \DateTimeImmutable())->modify('+1 hour'));

        $this->assertStringStartsWith('https://example.test/download?', $url);
        $this->assertStringContainsString('path=org-1%2Fnotes.txt', $url);
    }

    #[DataProvider('traversalAttempts')]
    public function test_rejects_path_traversal_attempts(string $maliciousPath): void
    {
        $this->expectException(RuntimeException::class);

        $this->storage()->put($maliciousPath, 'pwned');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function traversalAttempts(): array
    {
        return [
            'relative traversal' => ['../../etc/evil.txt'],
            'absolute path' => ['/etc/evil.txt'],
            'embedded traversal' => ['org-1/../../evil.txt'],
        ];
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
