<?php

declare(strict_types=1);

namespace Kontor\SDK\Tests\Scaffolding;

trait TempDirectoryTrait
{
    private function freshTempDirectory(): string
    {
        return sys_get_temp_dir().'/kontor-sdk-scaffold-'.bin2hex(random_bytes(8));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
