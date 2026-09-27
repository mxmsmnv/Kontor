<?php

declare(strict_types=1);

namespace Kontor\SDK\Tests\Scaffolding;

use Kontor\SDK\Scaffolding\ComponentScaffolder;
use PHPUnit\Framework\TestCase;

final class ComponentScaffolderTest extends TestCase
{
    use TempDirectoryTrait;

    private string $targetDir;

    protected function setUp(): void
    {
        $this->targetDir = $this->freshTempDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->targetDir);
    }

    public function test_dry_run_reports_the_full_canonical_tree_without_writing_anything(): void
    {
        $scaffolder = new ComponentScaffolder('Widgets', 'widgets', 'Kontor Widgets', 'Sample widgets component.');

        $files = $scaffolder->generate($this->targetDir, dryRun: true);

        $this->assertArrayHasKey('KontorWidgets.module.php', $files);
        $this->assertArrayHasKey('kontor.json', $files);
        $this->assertArrayHasKey('composer.json', $files);
        $this->assertArrayHasKey('LICENSE', $files);
        $this->assertArrayHasKey('README.md', $files);
        $this->assertArrayHasKey('CHANGELOG.md', $files);
        $this->assertArrayHasKey('src/Admin/.gitkeep', $files);
        $this->assertArrayHasKey('src/Support/.gitkeep', $files);
        $this->assertArrayHasKey('resources/translations/en/.gitkeep', $files);
        $this->assertArrayHasKey('resources/translations/es/.gitkeep', $files);
        $this->assertArrayHasKey('tests/E2E/.gitkeep', $files);
        $this->assertArrayHasKey('docs/.gitkeep', $files);
        $this->assertDirectoryDoesNotExist($this->targetDir);
    }

    public function test_generate_writes_valid_kontor_json_and_composer_json(): void
    {
        $scaffolder = new ComponentScaffolder('Widgets', 'widgets', 'Kontor Widgets', 'Sample widgets component.');

        $scaffolder->generate($this->targetDir);

        $kontorJson = json_decode((string) file_get_contents("{$this->targetDir}/kontor.json"), associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('KontorWidgets', $kontorJson['name']);
        $this->assertSame('Kontor\\Widgets', $kontorJson['namespace']);
        $this->assertSame('kontor/widgets', $kontorJson['package']);

        $composerJson = json_decode((string) file_get_contents("{$this->targetDir}/composer.json"), associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('kontor/widgets', $composerJson['name']);
        $this->assertArrayHasKey('Kontor\\Widgets\\', $composerJson['autoload']['psr-4']);
        $this->assertArrayHasKey('Kontor\\Widgets\\Migrations\\', $composerJson['autoload']['psr-4']);
    }

    public function test_generate_writes_a_syntactically_valid_module_file(): void
    {
        $scaffolder = new ComponentScaffolder('Widgets', 'widgets', 'Kontor Widgets', 'Sample widgets component.');

        $scaffolder->generate($this->targetDir);

        $modulePath = "{$this->targetDir}/KontorWidgets.module.php";
        $this->assertFileExists($modulePath);
        $this->assertStringContainsString('class KontorWidgets extends WireData implements Module', (string) file_get_contents($modulePath));

        exec('php -l '.escapeshellarg($modulePath), $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
