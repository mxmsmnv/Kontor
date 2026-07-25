<?php

declare(strict_types=1);

namespace Kontor\Reports\Tests\Unit\Application;

use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Documents\Infrastructure\Rendering\PdfRenderer;
use Kontor\Reports\Application\ReportExportService;
use Kontor\SDK\DTO\ReportResult;
use PHPUnit\Framework\TestCase;

/**
 * Unlike most of this monorepo's tests, this one needs no database — the
 * CSV/JSON/XLSX writers are pure filesystem, and dompdf is a pure-PHP
 * Composer dependency — so it runs for real in this sandbox.
 */
final class ReportExportServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/kontor-reports-test-' . bin2hex(random_bytes(8));
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        rmdir($this->tempDir);
    }

    private function sampleResult(): ReportResult
    {
        return new ReportResult(rows: [
            ['stage_uid' => 'lead', 'deal_count' => 4],
            ['stage_uid' => 'won', 'deal_count' => 2],
        ]);
    }

    private function service(): ReportExportService
    {
        return new ReportExportService(new FormatResolver(), new PdfRenderer());
    }

    public function test_exports_to_csv(): void
    {
        $path = $this->tempDir . '/report.csv';
        $this->service()->export($this->sampleResult(), ['stage_uid', 'deal_count'], 'csv', $path);

        $csv = file_get_contents($path);
        $this->assertStringContainsString('stage_uid,deal_count', $csv);
        $this->assertStringContainsString('lead,4', $csv);
    }

    public function test_exports_to_json(): void
    {
        $path = $this->tempDir . '/report.json';
        $this->service()->export($this->sampleResult(), ['stage_uid', 'deal_count'], 'json', $path);

        $decoded = json_decode(file_get_contents($path), associative: true);
        $this->assertSame([['stage_uid' => 'lead', 'deal_count' => 4], ['stage_uid' => 'won', 'deal_count' => 2]], $decoded);
    }

    public function test_exports_to_pdf(): void
    {
        $path = $this->tempDir . '/report.pdf';
        $this->service()->export($this->sampleResult(), ['stage_uid', 'deal_count'], 'pdf', $path);

        $pdf = file_get_contents($path);
        $this->assertStringStartsWith('%PDF-', $pdf);
    }
}
