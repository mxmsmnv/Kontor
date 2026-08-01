<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Unit\Application;

use Kontor\AI\Application\ExternalApprovalService;
use Kontor\AI\Migrations\Migration0002CreateExternalApprovalsTable;
use PHPUnit\Framework\TestCase;

final class ExternalApprovalServiceTest extends TestCase
{
    public function testMigrationIdentityIsStable(): void
    {
        $migration = new Migration0002CreateExternalApprovalsTable();
        self::assertSame('ai', $migration->component());
        self::assertSame('0002_create_external_approvals_table', $migration->name());
    }

    public function testOnlyRedactedBoundedMetadataSurvives(): void
    {
        $reflection = new \ReflectionClass(ExternalApprovalService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('redactedMetadata');
        $safe = $method->invoke($service, [
            'host' => 'Confirm.Example.com',
            'folder' => 'INBOX',
            'uid' => 42,
            'account_id' => 3,
            'body' => 'must not survive',
            'url' => 'https://confirm.example.com/secret?token=secret',
        ]);
        self::assertSame(['host', 'folder', 'uid', 'account_id', 'redacted'], array_keys($safe));
        self::assertSame('confirm.example.com', $safe['host']);
        self::assertTrue($safe['redacted']);
    }

    public function testMarkupInFolderIsRejected(): void
    {
        $reflection = new \ReflectionClass(ExternalApprovalService::class);
        $service = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('redactedMetadata');
        $this->expectException(\RuntimeException::class);
        $method->invoke($service, ['host' => 'example.com', 'folder' => '<script>', 'uid' => 1, 'account_id' => 1]);
    }
}
