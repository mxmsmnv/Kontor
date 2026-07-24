<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Installation;

use Kontor\Core\Domain\ComponentManifest;
use Kontor\Core\Domain\InvalidManifestException;
use Kontor\Core\Infrastructure\Manifest\ManifestReader;
use RuntimeException;
use ZipArchive;

/**
 * Safely extracts a component ZIP package (kontor.md section 25, "Kontor
 * ZIP package") into a components root directory. Every entry name is
 * validated before extraction to rule out path traversal ("zip slip");
 * extraction happens into a staging directory first so a bad archive never
 * touches the real target path.
 */
final class ZipInstaller
{
    public function __construct(
        private readonly ManifestReader $manifestReader = new ManifestReader(),
        private readonly int $maxTotalUncompressedBytes = 500 * 1024 * 1024,
        private readonly int $maxEntryCount = 20000,
    ) {
    }

    /**
     * @return array{manifest: ComponentManifest, path: string}
     */
    public function install(string $zipPath, string $targetRootDir, bool $overwrite = false): array
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open ZIP archive \"{$zipPath}\".");
        }

        $staging = $this->createStagingDirectory($targetRootDir);

        try {
            $this->extractSafely($zip, $staging);
        } finally {
            $zip->close();
        }

        $manifest = $this->readManifestFromStaging($staging);

        $finalPath = rtrim($targetRootDir, '/\\') . DIRECTORY_SEPARATOR . $manifest->name;

        if (is_dir($finalPath) && !$overwrite) {
            $this->removeDirectory($staging);

            throw new RuntimeException(
                "\"{$manifest->name}\" is already installed at \"{$finalPath}\". Pass overwrite=true to update it."
            );
        }

        if (is_dir($finalPath)) {
            $this->removeDirectory($finalPath);
        }

        if (!rename($staging, $finalPath)) {
            $this->removeDirectory($staging);

            throw new RuntimeException("Could not move extracted component into \"{$finalPath}\".");
        }

        return ['manifest' => $manifest, 'path' => $finalPath];
    }

    public function checksumOf(string $directory): string
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isFile()) {
                $files[$file->getPathname()] = hash_file('sha256', $file->getPathname());
            }
        }

        ksort($files);

        return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
    }

    private function createStagingDirectory(string $targetRootDir): string
    {
        $staging = rtrim($targetRootDir, '/\\') . DIRECTORY_SEPARATOR . '.staging-' . bin2hex(random_bytes(8));

        if (!is_dir($staging) && !mkdir($staging, 0775, true) && !is_dir($staging)) {
            throw new RuntimeException("Could not create staging directory \"{$staging}\".");
        }

        return $staging;
    }

    private function extractSafely(ZipArchive $zip, string $staging): void
    {
        if ($zip->numFiles > $this->maxEntryCount) {
            $this->removeDirectory($staging);

            throw new ZipSlipException("ZIP archive contains too many entries ({$zip->numFiles}).");
        }

        $totalSize = 0;
        $realStaging = realpath($staging) ?: $staging;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $stat = $zip->statIndex($i);

            if ($name === false || $stat === false) {
                continue;
            }

            $this->assertSafeEntryName($name, $staging, $realStaging);

            $totalSize += $stat['size'];

            if ($totalSize > $this->maxTotalUncompressedBytes) {
                $this->removeDirectory($staging);

                throw new ZipSlipException('ZIP archive exceeds the maximum allowed uncompressed size.');
            }
        }

        if (!$zip->extractTo($staging)) {
            $this->removeDirectory($staging);

            throw new RuntimeException('Failed to extract ZIP archive.');
        }
    }

    private function assertSafeEntryName(string $name, string $staging, string $realStaging): void
    {
        if (str_contains($name, "\0")) {
            $this->removeDirectory($staging);

            throw new ZipSlipException("ZIP entry \"{$name}\" contains a null byte.");
        }

        if (str_starts_with($name, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $name) === 1) {
            $this->removeDirectory($staging);

            throw new ZipSlipException("ZIP entry \"{$name}\" is an absolute path.");
        }

        $normalized = str_replace('\\', '/', $name);

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '..') {
                $this->removeDirectory($staging);

                throw new ZipSlipException("ZIP entry \"{$name}\" attempts to traverse outside the install directory.");
            }
        }

        $resolvedTarget = $realStaging . DIRECTORY_SEPARATOR . $normalized;

        if (!str_starts_with($resolvedTarget, $realStaging . DIRECTORY_SEPARATOR) && $resolvedTarget !== $realStaging) {
            $this->removeDirectory($staging);

            throw new ZipSlipException("ZIP entry \"{$name}\" resolves outside the install directory.");
        }
    }

    private function readManifestFromStaging(string $staging): ComponentManifest
    {
        $manifestPath = $staging . DIRECTORY_SEPARATOR . 'kontor.json';

        if (!is_file($manifestPath)) {
            $this->removeDirectory($staging);

            throw new InvalidManifestException('ZIP archive does not contain a kontor.json at its root.');
        }

        return $this->manifestReader->readFile($manifestPath);
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
