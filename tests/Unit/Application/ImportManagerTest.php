<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\ImportManager;
use Kontor\Core\Application\PreImportBackupRequiredException;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordWriter;
use Kontor\Core\Infrastructure\Registry\ImportProviderRegistry;
use Kontor\Core\Tests\Support\FakeContactImportProvider;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\ImportContext;
use PHPUnit\Framework\TestCase;

final class ImportManagerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/kontor-import-' . bin2hex(random_bytes(6)) . '.csv';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_dry_run_never_calls_import_and_previews_create_vs_update(): void
    {
        $this->writeCsv([
            ['name' => 'Acme', 'email' => 'new@acme.test'],
            ['name' => 'Widgets', 'email' => 'existing@widgets.test'],
        ]);

        $provider = new FakeContactImportProvider();
        $manager = $this->manager($provider);

        $result = $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(organizationId: 'org_01', batchId: 'batch_01', dryRun: true, actorType: 'user', actorId: 'usr_01'),
        );

        $this->assertTrue($result->dryRun);
        $this->assertSame(0, $provider->importCalls);
        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->updated);
        $this->assertSame('would_create', $result->rows[0]->outcome);
        $this->assertSame('would_update', $result->rows[1]->outcome);
    }

    public function test_invalid_rows_are_marked_failed_without_calling_find_existing_or_import(): void
    {
        $this->writeCsv([['name' => 'No Email', 'email' => '']]);

        $provider = new FakeContactImportProvider();
        $manager = $this->manager($provider);

        $result = $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(organizationId: 'org_01', batchId: 'batch_01', dryRun: true, actorType: 'user', actorId: 'usr_01'),
        );

        $this->assertSame(1, $result->failed);
        $this->assertSame(0, $provider->findExistingCalls);
        $this->assertStringContainsString('email', $result->rows[0]->errorMessage);
    }

    public function test_field_mapping_is_applied_before_validation(): void
    {
        $this->writeCsv([['full_name' => 'Acme', 'contact_email' => 'new@acme.test']]);

        $provider = new FakeContactImportProvider();
        $manager = $this->manager($provider);

        $result = $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(
                organizationId: 'org_01',
                batchId: 'batch_01',
                dryRun: true,
                actorType: 'user',
                actorId: 'usr_01',
                fieldMapping: ['full_name' => 'name', 'contact_email' => 'email'],
            ),
        );

        $this->assertSame(0, $result->failed);
        $this->assertSame(1, $result->created);
    }

    public function test_live_run_without_a_verified_backup_is_blocked(): void
    {
        $this->writeCsv([['name' => 'Acme', 'email' => 'new@acme.test']]);

        $manager = $this->manager(new FakeContactImportProvider());

        $this->expectException(PreImportBackupRequiredException::class);

        $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(organizationId: 'org_01', batchId: 'batch_01', dryRun: false, actorType: 'user', actorId: 'usr_01'),
        );
    }

    public function test_live_run_with_verified_backup_actually_imports(): void
    {
        $this->writeCsv([
            ['name' => 'Acme', 'email' => 'new@acme.test'],
            ['name' => 'Widgets', 'email' => 'existing@widgets.test'],
        ]);

        $provider = new FakeContactImportProvider();
        $manager = $this->manager($provider);

        $result = $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(organizationId: 'org_01', batchId: 'batch_01', dryRun: false, actorType: 'user', actorId: 'usr_01'),
            backupVerification: new BackupVerification(verified: true),
        );

        $this->assertSame(1, $result->created);
        $this->assertSame(1, $result->updated);
        $this->assertSame(2, $provider->importCalls);
    }

    public function test_live_run_proceeds_when_backup_is_bypassed(): void
    {
        $this->writeCsv([['name' => 'Acme', 'email' => 'new@acme.test']]);

        $manager = $this->manager(new FakeContactImportProvider());

        $result = $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(organizationId: 'org_01', batchId: 'batch_01', dryRun: false, actorType: 'user', actorId: 'usr_01'),
            bypassBackup: true,
        );

        $this->assertSame(1, $result->created);
    }

    public function test_completed_event_is_emitted_with_final_counts(): void
    {
        $this->writeCsv([['name' => 'Acme', 'email' => 'new@acme.test']]);

        $events = new RecordingEventDispatcher();
        $manager = new ImportManager(providers: $this->registry(new FakeContactImportProvider()), events: $events);

        $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(organizationId: 'org_01', batchId: 'batch_01', dryRun: true, actorType: 'user', actorId: 'usr_01'),
        );

        $completed = array_values(array_filter($events->dispatched, static fn ($e) => $e->event === 'import.previewed'));
        $this->assertCount(1, $completed);
        $this->assertSame(1, $completed[0]->data['created']);
    }

    private function registry(FakeContactImportProvider $provider): ImportProviderRegistry
    {
        $registry = new ImportProviderRegistry();
        $registry->register('contact', $provider);

        return $registry;
    }

    private function manager(FakeContactImportProvider $provider): ImportManager
    {
        return new ImportManager(providers: $this->registry($provider));
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    private function writeCsv(array $rows): void
    {
        $fields = array_keys($rows[0]);
        (new CsvRecordWriter())->write($this->path, $rows, $fields);
    }
}
