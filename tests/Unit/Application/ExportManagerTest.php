<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\ExportManager;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordWriter;
use Kontor\Core\Infrastructure\Registry\ExportProviderRegistry;
use Kontor\SDK\Contracts\ExportProviderInterface;
use Kontor\SDK\DTO\ExportContext;
use PHPUnit\Framework\TestCase;

final class ExportManagerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/kontor-export-' . bin2hex(random_bytes(6)) . '.csv';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_streams_provider_rows_into_the_writer_and_returns_the_total_count(): void
    {
        $registry = new ExportProviderRegistry();
        $registry->register('contact', new FakeContactExportProvider());

        $manager = new ExportManager($registry);
        $context = new ExportContext(organizationId: 'org_01', actorType: 'user', actorId: 'usr_01');

        $total = $manager->run('contact', [], ['name', 'email'], $context, new CsvRecordWriter(), $this->path);

        $this->assertSame(2, $total);
        $rows = iterator_to_array((new CsvRecordReader())->read($this->path));
        $this->assertSame([
            ['name' => 'Acme', 'email' => 'info@acme.test'],
            ['name' => 'Widgets', 'email' => 'hi@widgets.test'],
        ], $rows);
    }

    public function test_empty_fields_falls_back_to_the_providers_declared_fields(): void
    {
        $registry = new ExportProviderRegistry();
        $registry->register('contact', new FakeContactExportProvider());

        $manager = new ExportManager($registry);
        $context = new ExportContext(organizationId: 'org_01', actorType: 'user', actorId: 'usr_01');

        $manager->run('contact', [], [], $context, new CsvRecordWriter(), $this->path);

        $rows = iterator_to_array((new CsvRecordReader())->read($this->path));
        $this->assertSame(['name', 'email'], array_keys($rows[0]));
    }
}

final class FakeContactExportProvider implements ExportProviderInterface
{
    public function entityType(): string
    {
        return 'contact';
    }

    public function fields(): array
    {
        return ['name', 'email'];
    }

    public function filters(): array
    {
        return [];
    }

    public function count(array $filters, ExportContext $context): int
    {
        return 2;
    }

    public function iterate(array $filters, array $fields, ExportContext $context): iterable
    {
        yield ['name' => 'Acme', 'email' => 'info@acme.test'];
        yield ['name' => 'Widgets', 'email' => 'hi@widgets.test'];
    }
}
