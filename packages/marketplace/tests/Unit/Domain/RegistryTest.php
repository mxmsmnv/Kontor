<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Tests\Unit\Domain;

use Kontor\Marketplace\Domain\Registry;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function test_official_registry_is_always_trusted(): void
    {
        $registry = Registry::official('https://example.com/registry.json');

        $this->assertSame('official', $registry->name);
        $this->assertTrue($registry->trusted);
        $this->assertTrue($registry->isActive());
    }

    public function test_custom_registry_defaults_to_untrusted(): void
    {
        $registry = Registry::custom('third-party', 'https://example.com/registry.json');

        $this->assertFalse($registry->trusted);
    }

    public function test_custom_registry_can_be_explicitly_trusted(): void
    {
        $registry = Registry::custom('partner', 'https://example.com/registry.json', trusted: true);

        $this->assertTrue($registry->trusted);
    }

    public function test_record_sync_sets_last_synced_at(): void
    {
        $registry = Registry::custom('third-party', 'https://example.com/registry.json');
        $this->assertNull($registry->lastSyncedAt);

        $registry->recordSync();

        $this->assertNotNull($registry->lastSyncedAt);
    }

    public function test_disable_and_enable(): void
    {
        $registry = Registry::custom('third-party', 'https://example.com/registry.json');

        $registry->disable();
        $this->assertFalse($registry->isActive());

        $registry->enable();
        $this->assertTrue($registry->isActive());
    }
}
