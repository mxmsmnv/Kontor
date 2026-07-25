<?php

declare(strict_types=1);

namespace Kontor\SDK\Tests\Scaffolding;

use Kontor\SDK\Scaffolding\ReportScaffolder;
use PHPUnit\Framework\TestCase;

final class ReportScaffolderTest extends TestCase
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

    public function test_generate_writes_a_syntactically_valid_provider_implementing_the_interface(): void
    {
        $scaffolder = new ReportScaffolder(
            'Kontor\\Widgets\\Infrastructure\\Reports',
            'WidgetSummaryReportProvider',
            'widgets_summary',
            'Widget Summary',
            ['widget_count' => 'int'],
        );

        $scaffolder->generate($this->targetDir);

        $path = "{$this->targetDir}/WidgetSummaryReportProvider.php";
        $contents = (string) file_get_contents($path);

        $this->assertStringContainsString('final class WidgetSummaryReportProvider implements ReportProviderInterface', $contents);
        $this->assertStringContainsString("return 'widgets_summary';", $contents);
        $this->assertStringContainsString("return 'Widget Summary';", $contents);
        $this->assertStringContainsString("'widget_count' => 'int'", $contents);

        exec('php -l '.escapeshellarg($path), $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));
    }

    public function test_generate_falls_back_to_a_placeholder_field_when_none_given(): void
    {
        $scaffolder = new ReportScaffolder('Kontor\\Widgets\\Infrastructure\\Reports', 'EmptyReportProvider', 'empty', 'Empty');

        $files = $scaffolder->generate($this->targetDir, dryRun: true);

        $this->assertStringContainsString("['result' => 'string']", $files['EmptyReportProvider.php']);
    }
}
