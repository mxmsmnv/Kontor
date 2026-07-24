<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Infrastructure\Backup;

use Kontor\Core\Application\BackupManager;
use Kontor\Core\Infrastructure\Backup\CoreBackupProvider;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\BackupProviderRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Tests\Integration\DatabaseTestCase;

final class CoreBackupProviderTest extends DatabaseTestCase
{
    private string $backupDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupDir = sys_get_temp_dir() . '/kontor-core-backup-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->removeDirectory($this->backupDir);
    }

    public function test_backup_then_restore_round_trips_real_data(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $organizations->defaultOrganization('DE', 'de', 'EUR');
        (new ComponentRegistry($this->pdo))->markInstalled('core', '0.1.0', 'local');

        $registry = new BackupProviderRegistry();
        $registry->register('core', new CoreBackupProvider($this->pdo));
        $manager = new BackupManager($registry, $this->backupDir);

        $record = $manager->create('core', 'full', 'org_01');

        $this->assertTrue($record->verified, implode('; ', $record->verificationErrors));
        $this->assertGreaterThanOrEqual(2, $record->itemCount);

        // simulate data loss, then restore from the verified backup
        $this->pdo->exec('DELETE FROM kontor_organizations');
        $this->pdo->exec('DELETE FROM kontor_components');

        $result = $manager->restore($record->path, 'core', 'org_01');

        $this->assertTrue($result->success);
        $this->assertSame(
            1,
            (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_organizations')->fetchColumn()
        );
        $this->assertSame(
            'core',
            $this->pdo->query("SELECT name FROM kontor_components WHERE name = 'core'")->fetchColumn()
        );
    }

    public function test_verify_detects_tampering_between_export_and_verification(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $organizations->defaultOrganization('DE', 'de', 'EUR');

        $registry = new BackupProviderRegistry();
        $registry->register('core', new CoreBackupProvider($this->pdo));
        $manager = new BackupManager($registry, $this->backupDir);

        $record = $manager->create('core', 'full', 'org_01');
        $this->assertTrue($record->verified);

        // corrupt the on-disk backup after the fact
        file_put_contents($record->path . '/tables/kontor_organizations.jsonl', "{\"tampered\":true}\n");

        $stillVerified = $manager->verify($record->path, 'core', 'org_01');

        $this->assertFalse($stillVerified);
    }

    public function test_dry_run_restore_does_not_modify_the_database(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $organizations->defaultOrganization('DE', 'de', 'EUR');

        $registry = new BackupProviderRegistry();
        $registry->register('core', new CoreBackupProvider($this->pdo));
        $manager = new BackupManager($registry, $this->backupDir);

        $record = $manager->create('core', 'full', 'org_01');
        $this->pdo->exec('DELETE FROM kontor_organizations');

        $result = $manager->restore($record->path, 'core', 'org_01', dryRun: true);

        $this->assertTrue($result->success);
        $this->assertSame(
            0,
            (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_organizations')->fetchColumn()
        );
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
