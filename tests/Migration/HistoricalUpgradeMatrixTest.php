<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Migration;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PHPUnit\Framework\TestCase;

final class HistoricalUpgradeMatrixTest extends TestCase
{
    private \PDO $pdo;

    protected function setUp(): void
    {
        $dsn = getenv('KONTOR_TEST_DB_DSN');
        if ($dsn === false) {
            $this->markTestSkipped('Set KONTOR_TEST_DB_DSN to a disposable MySQL/MariaDB database.');
        }

        $this->pdo = new \PDO(
            $dsn,
            getenv('KONTOR_TEST_DB_USER') ?: null,
            getenv('KONTOR_TEST_DB_PASS') ?: null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
        );
        $this->cleanSchema();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->cleanSchema();
        }
    }

    public function test_v001_schema_and_data_upgrade_to_every_current_component(): void
    {
        $matrix = $this->matrix();
        $runner = new MigrationRunner($this->pdo);
        $runner->ensureLedgerExists();
        $currentByComponent = [];
        $historicalCount = 0;
        $currentCount = 0;

        foreach ($matrix as $entry) {
            if ($entry['component'] === null) {
                continue;
            }
            $current = $this->migrationsFor($entry);
            $currentByComponent[$entry['component']] = $current;
            $pending = array_flip($entry['pendingMigrations']);
            $historical = array_values(array_filter(
                $current,
                static fn (MigrationInterface $migration): bool => !isset($pending[$migration::class]),
            ));
            $outcomes = $runner->run($historical);
            self::assertSame(
                array_fill_keys(array_keys($outcomes), 'ok'),
                $outcomes,
                $entry['component'] . ' historical v001 migrations must apply cleanly.',
            );
            $historicalCount += count($historical);
            $currentCount += count($current);
        }

        self::assertFalse($this->tableExists('kontor_ai_external_approvals'));
        $organization = (new OrganizationRepository($this->pdo))->defaultOrganization('US', 'en', 'EUR');
        $organizationUid = $organization->uid->toString();

        foreach ($matrix as $entry) {
            if ($entry['component'] === null || $entry['pendingMigrations'] === []) {
                continue;
            }
            $byClass = [];
            foreach ($currentByComponent[$entry['component']] as $migration) {
                $byClass[$migration::class] = $migration;
            }
            $pending = array_map(
                static fn (string $class): MigrationInterface => $byClass[$class],
                $entry['pendingMigrations'],
            );
            $outcomes = $runner->run($pending);
            self::assertSame(array_fill_keys(array_keys($outcomes), 'ok'), $outcomes);
        }

        self::assertTrue($this->tableExists('kontor_ai_external_approvals'));
        self::assertSame(
            $organizationUid,
            (new OrganizationRepository($this->pdo))->require($organizationUid)->uid->toString(),
            'Upgrade must preserve records created by the historical schema.',
        );

        foreach ($currentByComponent as $component => $migrations) {
            $outcomes = $runner->run($migrations);
            self::assertSame(
                array_fill_keys(array_keys($outcomes), 'skipped'),
                $outcomes,
                $component . ' current migrations must be idempotent after upgrade.',
            );
        }

        self::assertGreaterThan(0, $historicalCount);
        self::assertSame(
            $currentCount,
            (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_migrations WHERE status = 'ok'")->fetchColumn(),
        );
        $this->assertDeclaredStorageTablesExist($matrix);
    }

    /** @param array<string, mixed> $entry
     *  @return MigrationInterface[]
     */
    private function migrationsFor(array $entry): array
    {
        if ($entry['component'] === null) {
            return [];
        }

        $root = dirname(__DIR__, 2);
        $moduleDirectory = dirname($root . '/' . $entry['modulePath']);
        $migrationDirectory = $entry['component'] === 'core'
            ? $root . '/migrations'
            : $moduleDirectory . '/migrations';
        $migrations = [];

        foreach (glob($migrationDirectory . '/Migration*.php') ?: [] as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertMatchesRegularExpression('/namespace\s+([^;]+);/', $source);
            self::assertMatchesRegularExpression('/final class\s+(Migration\w+)/', $source);
            preg_match('/namespace\s+([^;]+);/', $source, $namespace);
            preg_match('/final class\s+(Migration\w+)/', $source, $className);
            $class = $namespace[1] . '\\' . $className[1];
            $migration = new $class();
            self::assertInstanceOf(MigrationInterface::class, $migration);
            self::assertSame($entry['component'], $migration->component());
            $migrations[] = $migration;
        }

        usort(
            $migrations,
            static fn (MigrationInterface $left, MigrationInterface $right): int => $left->name() <=> $right->name(),
        );

        return $migrations;
    }

    /** @param array<int, array<string, mixed>> $matrix */
    private function assertDeclaredStorageTablesExist(array $matrix): void
    {
        foreach ($matrix as $entry) {
            if (!str_starts_with($entry['modulePath'], 'packages/')) {
                continue;
            }
            $manifestPath = dirname(__DIR__, 2) . '/' . dirname($entry['modulePath']) . '/kontor.json';
            $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
            foreach ($manifest['storage']['tables'] ?? [] as $table) {
                self::assertTrue($this->tableExists($table), $entry['moduleName'] . ' table is missing: ' . $table);
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
        );
        $statement->execute(['table' => $table]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function cleanSchema(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (array_reverse($this->matrix()) as $entry) {
            foreach (array_reverse($this->migrationsFor($entry)) as $migration) {
                $migration->down($this->pdo);
            }
        }
        $this->pdo->exec('DROP TABLE IF EXISTS kontor_migrations');
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array<int, array<string, mixed>> */
    private function matrix(): array
    {
        return json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/Fixtures/historical-upgrade-v001.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
