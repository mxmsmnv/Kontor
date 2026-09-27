<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Registry;

use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use PHPUnit\Framework\TestCase;

interface FakeCrmServiceInterface
{
}

final class FakeCrmService implements FakeCrmServiceInterface
{
}

final class CapabilityRegistryTest extends TestCase
{
    public function test_register_and_get(): void
    {
        $registry = new CapabilityRegistry();
        $service = new FakeCrmService();

        $registry->register('crm', '1.0', FakeCrmServiceInterface::class, $service, 'KontorCRM');

        $this->assertTrue($registry->has('crm'));
        $this->assertSame($service, $registry->get('crm'));
    }

    public function test_register_rejects_implementation_not_matching_contract(): void
    {
        $registry = new CapabilityRegistry();

        $this->expectException(\RuntimeException::class);

        $registry->register('crm', '1.0', FakeCrmServiceInterface::class, new \stdClass(), 'KontorCRM');
    }

    public function test_has_respects_caret_version_constraint(): void
    {
        $registry = new CapabilityRegistry();
        $registry->register('crm', '1.2', FakeCrmServiceInterface::class, new FakeCrmService(), 'KontorCRM');

        $this->assertTrue($registry->has('crm', '^1.0'));
        $this->assertFalse($registry->has('crm', '^2.0'));
    }

    public function test_get_throws_when_capability_missing(): void
    {
        $registry = new CapabilityRegistry();

        $this->expectException(\RuntimeException::class);

        $registry->get('crm');
    }

    public function test_all_returns_every_registered_implementation(): void
    {
        $registry = new CapabilityRegistry();
        $crm = new FakeCrmService();

        $registry->register('crm', '1.0', FakeCrmServiceInterface::class, $crm, 'KontorCRM');

        $this->assertSame(['crm' => $crm], $registry->all());
    }
}
