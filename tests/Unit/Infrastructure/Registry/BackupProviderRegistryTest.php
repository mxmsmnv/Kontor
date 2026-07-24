<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Registry;

use Kontor\Core\Infrastructure\Registry\BackupProviderRegistry;
use Kontor\SDK\Contracts\BackupProviderInterface;
use Kontor\SDK\DTO\BackupContext;
use Kontor\SDK\DTO\BackupEstimate;
use Kontor\SDK\DTO\BackupReader;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\BackupWriter;
use Kontor\SDK\DTO\RestoreContext;
use Kontor\SDK\DTO\RestoreResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BackupProviderRegistryTest extends TestCase
{
    public function test_register_and_get(): void
    {
        $registry = new BackupProviderRegistry();
        $provider = new FakeBackupProvider('KontorCRM');

        $registry->register('KontorCRM', $provider);

        $this->assertTrue($registry->has('KontorCRM'));
        $this->assertSame($provider, $registry->get('KontorCRM'));
    }

    public function test_get_throws_for_unregistered_component(): void
    {
        $registry = new BackupProviderRegistry();

        $this->expectException(RuntimeException::class);

        $registry->get('KontorGhost');
    }

    public function test_all_returns_every_registered_provider(): void
    {
        $registry = new BackupProviderRegistry();
        $core = new FakeBackupProvider('core');
        $crm = new FakeBackupProvider('KontorCRM');

        $registry->register('core', $core);
        $registry->register('KontorCRM', $crm);

        $this->assertSame(['core' => $core, 'KontorCRM' => $crm], $registry->all());
    }
}

final class FakeBackupProvider implements BackupProviderInterface
{
    public function __construct(private readonly string $componentName)
    {
    }

    public function component(): string
    {
        return $this->componentName;
    }

    public function estimate(BackupContext $context): BackupEstimate
    {
        return new BackupEstimate(itemCount: 0, estimatedSizeBytes: 0);
    }

    public function export(BackupWriter $writer, BackupContext $context): void
    {
    }

    public function verify(BackupReader $reader, BackupContext $context): BackupVerification
    {
        return new BackupVerification(verified: true);
    }

    public function restore(BackupReader $reader, RestoreContext $context): RestoreResult
    {
        return new RestoreResult(success: true);
    }
}
