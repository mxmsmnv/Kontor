<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Infrastructure\Registry;

use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Tests\Integration\DatabaseTestCase;
use RuntimeException;

final class ComponentRegistryTest extends DatabaseTestCase
{
    public function test_install_then_enable_then_disable(): void
    {
        $registry = new ComponentRegistry($this->pdo);

        $registry->markInstalled('KontorCRM', '1.0.0', 'local');
        $this->assertFalse($registry->isEnabled('KontorCRM'));

        $registry->enable('KontorCRM');
        $this->assertTrue($registry->isEnabled('KontorCRM'));

        $registry->disable('KontorCRM');
        $this->assertFalse($registry->isEnabled('KontorCRM'));
    }

    public function test_reinstalling_updates_version_without_duplicating_row(): void
    {
        $registry = new ComponentRegistry($this->pdo);

        $registry->markInstalled('KontorCRM', '1.0.0', 'local');
        $registry->markInstalled('KontorCRM', '1.1.0', 'local');

        $this->assertCount(1, $registry->all());
        $this->assertSame('1.1.0', $registry->find('KontorCRM')['version']);
    }

    public function test_enabling_an_unregistered_component_throws(): void
    {
        $registry = new ComponentRegistry($this->pdo);

        $this->expectException(RuntimeException::class);

        $registry->enable('KontorGhost');
    }
}
