<?php

declare(strict_types=1);

namespace Kontor\SDK\Tests\Scaffolding;

use Kontor\SDK\Scaffolding\EntityScaffolder;
use PHPUnit\Framework\TestCase;

final class EntityScaffolderTest extends TestCase
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

    public function test_dry_run_reports_domain_and_repository_files_without_writing_anything(): void
    {
        $scaffolder = new EntityScaffolder('Kontor\\Widgets', 'Widget', 'kontor_widgets');

        $files = $scaffolder->generate($this->targetDir, dryRun: true);

        $this->assertArrayHasKey('src/Domain/Widget.php', $files);
        $this->assertArrayHasKey('src/Infrastructure/Persistence/WidgetRepository.php', $files);
        $this->assertDirectoryDoesNotExist($this->targetDir);
    }

    public function test_generate_writes_a_syntactically_valid_domain_entity_and_repository(): void
    {
        $scaffolder = new EntityScaffolder('Kontor\\Widgets', 'Widget', 'kontor_widgets');

        $scaffolder->generate($this->targetDir);

        $domainPath = "{$this->targetDir}/src/Domain/Widget.php";
        $repositoryPath = "{$this->targetDir}/src/Infrastructure/Persistence/WidgetRepository.php";

        $this->assertStringContainsString('final class Widget', (string) file_get_contents($domainPath));
        $this->assertStringContainsString('final class WidgetRepository implements RepositoryInterface', (string) file_get_contents($repositoryPath));
        $this->assertStringContainsString('FROM kontor_widgets', (string) file_get_contents($repositoryPath));

        foreach ([$domainPath, $repositoryPath] as $path) {
            exec('php -l '.escapeshellarg($path), $output, $exitCode);
            $this->assertSame(0, $exitCode, implode("\n", $output));
        }
    }
}
