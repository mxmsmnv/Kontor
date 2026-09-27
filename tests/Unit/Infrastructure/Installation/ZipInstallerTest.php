<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Installation;

use Kontor\Core\Domain\InvalidManifestException;
use Kontor\Core\Infrastructure\Installation\ZipInstaller;
use Kontor\Core\Infrastructure\Installation\ZipSlipException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ZipInstallerTest extends TestCase
{
    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/kontor-zip-' . bin2hex(random_bytes(6));
        mkdir($this->workDir . '/target', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->workDir);
    }

    public function test_installs_a_well_formed_component_zip(): void
    {
        $zipPath = $this->buildZip([
            'kontor.json' => $this->manifestJson('KontorCRM'),
            'src/Plugin.php' => '<?php // noop',
        ]);

        $result = (new ZipInstaller())->install($zipPath, $this->workDir . '/target');

        $this->assertSame('KontorCRM', $result['manifest']->name);
        $this->assertFileExists($result['path'] . '/kontor.json');
        $this->assertFileExists($result['path'] . '/src/Plugin.php');
        $this->assertDirectoryDoesNotExist($this->workDir . '/target/.staging');
    }

    public function test_rejects_a_zip_with_a_directory_traversal_entry(): void
    {
        $zipPath = $this->buildZip([
            'kontor.json' => $this->manifestJson('KontorCRM'),
            '../../evil.php' => '<?php echo "pwned";',
        ]);

        $this->expectException(ZipSlipException::class);

        (new ZipInstaller())->install($zipPath, $this->workDir . '/target');
    }

    public function test_rejects_a_zip_with_an_absolute_path_entry(): void
    {
        $zipPath = $this->buildZip([
            'kontor.json' => $this->manifestJson('KontorCRM'),
            '/etc/evil.php' => '<?php echo "pwned";',
        ]);

        $this->expectException(ZipSlipException::class);

        (new ZipInstaller())->install($zipPath, $this->workDir . '/target');
    }

    public function test_a_rejected_zip_leaves_no_files_outside_the_target_root(): void
    {
        $zipPath = $this->buildZip([
            'kontor.json' => $this->manifestJson('KontorCRM'),
            '../escaped.php' => '<?php echo "pwned";',
        ]);

        try {
            (new ZipInstaller())->install($zipPath, $this->workDir . '/target');
        } catch (ZipSlipException) {
            // expected
        }

        $this->assertFileDoesNotExist($this->workDir . '/escaped.php');
        $this->assertFileDoesNotExist(dirname($this->workDir) . '/escaped.php');
    }

    public function test_rejects_a_zip_without_a_root_manifest(): void
    {
        $zipPath = $this->buildZip(['README.md' => 'no manifest here']);

        $this->expectException(InvalidManifestException::class);

        (new ZipInstaller())->install($zipPath, $this->workDir . '/target');
    }

    public function test_refuses_to_overwrite_an_existing_install_by_default(): void
    {
        $zipPath = $this->buildZip(['kontor.json' => $this->manifestJson('KontorCRM')]);
        $installer = new ZipInstaller();

        $installer->install($zipPath, $this->workDir . '/target');

        $this->expectException(\RuntimeException::class);

        $installer->install($zipPath, $this->workDir . '/target');
    }

    public function test_overwrite_true_replaces_an_existing_install(): void
    {
        $installer = new ZipInstaller();
        $installer->install(
            $this->buildZip(['kontor.json' => $this->manifestJson('KontorCRM', '1.0.0')]),
            $this->workDir . '/target'
        );

        $result = $installer->install(
            $this->buildZip(['kontor.json' => $this->manifestJson('KontorCRM', '1.1.0')]),
            $this->workDir . '/target',
            overwrite: true
        );

        $this->assertSame('1.1.0', $result['manifest']->version);
    }

    private function manifestJson(string $name, string $version = '1.0.0'): string
    {
        return json_encode([
            'name' => $name,
            'version' => $version,
            'package' => 'kontor/' . strtolower($name),
            'namespace' => 'Kontor\\Test',
            'requires' => [],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, string> $entries local name => contents
     */
    private function buildZip(array $entries): string
    {
        $path = $this->workDir . '/component-' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $path;
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
