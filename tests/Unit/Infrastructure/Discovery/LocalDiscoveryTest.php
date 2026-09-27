<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Discovery;

use Kontor\Core\Infrastructure\Discovery\LocalDiscovery;
use PHPUnit\Framework\TestCase;

final class LocalDiscoveryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kontor-discovery-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);
    }

    public function test_scan_finds_manifests_in_immediate_subdirectories(): void
    {
        $this->writeComponent('KontorCRM', 'kontor/crm');
        $this->writeComponent('KontorContacts', 'kontor/contacts');

        $manifests = (new LocalDiscovery())->scan($this->root);

        $this->assertCount(2, $manifests);
        $names = array_map(static fn ($m) => $m->name, $manifests);
        sort($names);
        $this->assertSame(['KontorCRM', 'KontorContacts'], $names);
    }

    public function test_scan_ignores_directories_without_a_manifest(): void
    {
        mkdir($this->root . '/NotAComponent');

        $manifests = (new LocalDiscovery())->scan($this->root);

        $this->assertSame([], $manifests);
    }

    public function test_scan_skips_an_unparseable_manifest_instead_of_throwing(): void
    {
        mkdir($this->root . '/Broken');
        file_put_contents($this->root . '/Broken/kontor.json', '{not json');
        $this->writeComponent('KontorCRM', 'kontor/crm');

        $manifests = (new LocalDiscovery())->scan($this->root);

        $this->assertCount(1, $manifests);
        $this->assertSame('KontorCRM', $manifests[0]->name);
    }

    public function test_scan_returns_empty_for_a_missing_root(): void
    {
        $this->assertSame([], (new LocalDiscovery())->scan($this->root . '/does-not-exist'));
    }

    private function writeComponent(string $name, string $package): void
    {
        $dir = $this->root . '/' . $name;
        mkdir($dir, 0775, true);
        file_put_contents($dir . '/kontor.json', json_encode([
            'name' => $name,
            'version' => '1.0.0',
            'package' => $package,
            'namespace' => 'Kontor\\Test',
            'requires' => [],
        ], JSON_THROW_ON_ERROR));
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
