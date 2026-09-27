<?php

declare(strict_types=1);

namespace Kontor\Files\Infrastructure\Storage;

use Kontor\SDK\Contracts\StorageInterface;
use Kontor\SDK\DTO\StoredFile;
use RuntimeException;

/**
 * Local, private-by-default storage adapter (Substage 2.2 "local private
 * storage"). Implements the SDK's StorageInterface (kontor.md#9.11) — the
 * "external adapter interface" milestone is this contract itself: a
 * KontorS3/KontorR2/etc. integration component (spec section 5.5) would
 * provide the exact same interface over a different transport, and
 * FileManager depends only on StorageInterface, never on this class.
 *
 * The root directory must be outside the web-servable document root, or
 * otherwise blocked at the webserver level — this class only enforces that
 * paths cannot escape the root via traversal, not that the root itself is
 * unreachable over HTTP.
 */
final class LocalPrivateStorage implements StorageInterface
{
    public function __construct(
        private readonly string $rootDir,
        private readonly SignedUrlSigner $signer,
    ) {
        if (!is_dir($rootDir) && !mkdir($rootDir, 0770, true) && !is_dir($rootDir)) {
            throw new RuntimeException("Could not create storage directory \"{$rootDir}\".");
        }
    }

    public function put(string $path, mixed $contents, array $options = []): StoredFile
    {
        $target = $this->resolve($path);
        $directory = dirname($target);

        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create directory \"{$directory}\".");
        }

        $data = is_resource($contents) ? stream_get_contents($contents) : (string) $contents;

        if (file_put_contents($target, $data) === false) {
            throw new RuntimeException("Could not write file \"{$path}\".");
        }

        return new StoredFile(
            path: $path,
            storage: 'local',
            sizeBytes: strlen($data),
            checksum: hash('sha256', $data),
            mimeType: $options['mimeType'] ?? $this->detectMimeType($target),
        );
    }

    public function read(string $path)
    {
        $target = $this->resolve($path);

        if (!is_file($target)) {
            throw new RuntimeException("File \"{$path}\" was not found in local storage.");
        }

        return fopen($target, 'rb');
    }

    public function delete(string $path): void
    {
        $target = $this->resolve($path);

        if (is_file($target)) {
            unlink($target);
        }
    }

    public function exists(string $path): bool
    {
        return is_file($this->resolve($path));
    }

    public function temporaryUrl(string $path, \DateTimeImmutable $expiresAt): string
    {
        return $this->signer->sign($path, $expiresAt);
    }

    private function resolve(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, '..')) {
            throw new RuntimeException("Invalid storage path \"{$path}\".");
        }

        return rtrim($this->rootDir, '/\\') . '/' . $normalized;
    }

    private function detectMimeType(string $filePath): ?string
    {
        return function_exists('mime_content_type') ? (mime_content_type($filePath) ?: null) : null;
    }
}
