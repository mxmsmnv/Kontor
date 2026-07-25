<?php

declare(strict_types=1);

namespace Kontor\SDK\Tests\Scaffolding;

use Kontor\SDK\Scaffolding\MigrationScaffolder;
use PHPUnit\Framework\TestCase;

final class MigrationScaffolderTest extends TestCase
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

    public function test_generate_pads_the_migration_number_and_studly_cases_the_class_name(): void
    {
        $scaffolder = new MigrationScaffolder('Kontor\\Widgets\\Migrations', 'widgets', 1, 'create_widgets_table', 'kontor_widgets');

        $files = $scaffolder->generate($this->targetDir, dryRun: true);

        $this->assertArrayHasKey('Migration0001CreateWidgetsTable.php', $files);
    }

    public function test_generate_writes_a_syntactically_valid_migration_implementing_the_interface(): void
    {
        $scaffolder = new MigrationScaffolder('Kontor\\Widgets\\Migrations', 'widgets', 1, 'create_widgets_table', 'kontor_widgets');

        $scaffolder->generate($this->targetDir);

        $path = "{$this->targetDir}/Migration0001CreateWidgetsTable.php";
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('final class Migration0001CreateWidgetsTable implements MigrationInterface', $contents);
        $this->assertStringContainsString("return 'widgets';", $contents);
        $this->assertStringContainsString("return '0001_create_widgets_table';", $contents);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS kontor_widgets', $contents);
        $this->assertStringContainsString('DROP TABLE IF EXISTS kontor_widgets', $contents);

        exec('php -l '.escapeshellarg($path), $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
