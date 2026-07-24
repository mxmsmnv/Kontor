<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Application;

use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Application\ImportManager;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordReader;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordWriter;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ImportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\RepositoryRegistry;
use Kontor\Core\Tests\Integration\DatabaseTestCase;
use Kontor\Core\Tests\Support\FakeContactImportProvider;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\ImportContext;
use RuntimeException;

final class ImportManagerRollbackTest extends DatabaseTestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->path !== '' && is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function test_rollback_archives_every_record_the_batch_created_or_updated(): void
    {
        $this->path = sys_get_temp_dir() . '/kontor-import-rollback-' . bin2hex(random_bytes(6)) . '.csv';
        (new CsvRecordWriter())->write($this->path, [
            ['name' => 'Acme', 'email' => 'new@acme.test'],
            ['name' => 'Widgets', 'email' => 'existing@widgets.test'],
        ], ['name', 'email']);

        $organizations = new OrganizationRepository($this->pdo);
        $organizationId = $organizations->internalIdOf(
            $organizations->defaultOrganization('US', 'en', 'EUR')->uid->toString()
        );

        $providerRegistry = new ImportProviderRegistry();
        $providerRegistry->register('contact', new FakeContactImportProvider());

        $repository = new RecordingRepository();
        $repositoryRegistry = new RepositoryRegistry();
        $repositoryRegistry->register('contact', $repository);

        $manager = new ImportManager(
            providers: $providerRegistry,
            repositories: $repositoryRegistry,
            audit: new AuditLogger($this->pdo),
            pdo: $this->pdo,
        );

        $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(organizationId: 'org_01', batchId: 'batch_rollback_01', dryRun: false, actorType: 'user', actorId: 'usr_01'),
            backupVerification: new BackupVerification(verified: true),
            auditOrganizationId: $organizationId,
        );

        $result = $manager->rollback('batch_rollback_01', 'contact');

        $this->assertSame(2, $result->archivedCount);
        $this->assertSame([], $result->errors);
        $this->assertEqualsCanonicalizing(['cnt_new_1', 'cnt_existing_01'], $repository->archived);
    }

    public function test_rollback_reports_per_entity_errors_without_stopping(): void
    {
        $this->path = sys_get_temp_dir() . '/kontor-import-rollback-' . bin2hex(random_bytes(6)) . '.csv';
        (new CsvRecordWriter())->write($this->path, [
            ['name' => 'Acme', 'email' => 'new@acme.test'],
        ], ['name', 'email']);

        $organizations = new OrganizationRepository($this->pdo);
        $organizationId = $organizations->internalIdOf(
            $organizations->defaultOrganization('US', 'en', 'EUR')->uid->toString()
        );

        $providerRegistry = new ImportProviderRegistry();
        $providerRegistry->register('contact', new FakeContactImportProvider());

        $repository = new RecordingRepository(failFor: ['cnt_new_1']);
        $repositoryRegistry = new RepositoryRegistry();
        $repositoryRegistry->register('contact', $repository);

        $manager = new ImportManager(
            providers: $providerRegistry,
            repositories: $repositoryRegistry,
            audit: new AuditLogger($this->pdo),
            pdo: $this->pdo,
        );

        $manager->run(
            'contact',
            new CsvRecordReader(),
            $this->path,
            new ImportContext(organizationId: 'org_01', batchId: 'batch_rollback_02', dryRun: false, actorType: 'user', actorId: 'usr_01'),
            bypassBackup: true,
            auditOrganizationId: $organizationId,
        );

        $result = $manager->rollback('batch_rollback_02', 'contact');

        $this->assertSame(0, $result->archivedCount);
        $this->assertNotEmpty($result->errors);
    }

    public function test_rollback_without_pdo_throws(): void
    {
        $manager = new ImportManager(providers: new ImportProviderRegistry());

        $this->expectException(RuntimeException::class);

        $manager->rollback('batch_01', 'contact');
    }

    public function test_rollback_without_a_registered_repository_throws(): void
    {
        $manager = new ImportManager(providers: new ImportProviderRegistry(), pdo: $this->pdo);

        $this->expectException(RuntimeException::class);

        $manager->rollback('batch_01', 'contact');
    }
}

final class RecordingRepository implements RepositoryInterface
{
    /** @var string[] */
    public array $archived = [];

    /**
     * @param string[] $failFor
     */
    public function __construct(private readonly array $failFor = [])
    {
    }

    public function find(string $id): ?object
    {
        return null;
    }

    public function require(string $id): object
    {
        return new \stdClass();
    }

    public function save(object $entity): void
    {
    }

    public function archive(string $id): void
    {
        if (in_array($id, $this->failFor, true)) {
            throw new RuntimeException("simulated failure archiving {$id}");
        }

        $this->archived[] = $id;
    }

    public function restore(string $id): void
    {
    }
}
