<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Health;

use Kontor\Core\Health\CoreHealthCheck;
use PHPUnit\Framework\TestCase;

final class CoreHealthCheckTest extends TestCase
{
    public function test_run_reports_ready_database_and_storage(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE kontor_organizations (id INTEGER PRIMARY KEY)');
        $pdo->exec('CREATE TABLE kontor_components (id INTEGER PRIMARY KEY, status TEXT NOT NULL)');
        $pdo->exec('INSERT INTO kontor_organizations DEFAULT VALUES');
        $pdo->exec("INSERT INTO kontor_components (status) VALUES ('enabled'), ('disabled')");

        $result = (new CoreHealthCheck(
            $pdo,
            sys_get_temp_dir() . '/kontor-health-check-storage'
        ))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['organizations']);
        $this->assertSame(1, $result->details['enabledComponents']);
        $this->assertTrue($result->details['backupStorageWritable']);
    }

    public function test_run_contains_database_failures(): void
    {
        $result = (new CoreHealthCheck(
            new \PDO('sqlite::memory:'),
            sys_get_temp_dir()
        ))->run();

        $this->assertSame('critical', $result->status);
        $this->assertStringContainsString('not reachable', $result->message);
    }
}
