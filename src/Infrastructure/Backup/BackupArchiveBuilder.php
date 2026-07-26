<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Backup;

final class BackupArchiveBuilder
{
    public function create(string $backupPath, string $destination): void
    {
        $root = realpath($backupPath);

        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException('Backup directory was not found.');
        }

        $archive = new \ZipArchive();

        if ($archive->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create backup archive.');
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                /** @var \SplFileInfo $file */
                if (!$file->isFile() || $file->isLink()) {
                    continue;
                }

                $path = $file->getRealPath();

                if ($path === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                    throw new \RuntimeException('Backup contains a file outside its storage directory.');
                }

                $relative = substr($path, strlen($root) + 1);

                if (!$archive->addFile($path, str_replace(DIRECTORY_SEPARATOR, '/', $relative))) {
                    throw new \RuntimeException("Could not add \"{$relative}\" to backup archive.");
                }
            }
        } finally {
            $archive->close();
        }
    }
}
