<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\ComponentManager;
use Kontor\Core\Application\ComponentNotInstalledException;
use Kontor\Core\Application\DependencyCheckFailedException;
use Kontor\Core\Application\PreUpdateBackupRequiredException;
use Kontor\Core\Domain\ComponentManifest;
use Kontor\Core\Infrastructure\Discovery\LocalDiscovery;
use Kontor\Core\Infrastructure\Registry\ComponentRegistryInterface;
use Kontor\SDK\Contracts\ComponentInterface;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\ComponentContext;
use Kontor\SDK\Events\KontorEvent;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ComponentManagerTest extends TestCase
{
    private InMemoryComponentRegistry $registry;
    private RecordingEventDispatcher $events;
    private ComponentManager $manager;

    protected function setUp(): void
    {
        $this->registry = new InMemoryComponentRegistry();
        $this->events = new RecordingEventDispatcher();
        $this->manager = new ComponentManager(registry: $this->registry, events: $this->events);
    }

    public function test_install_registers_enables_boots_and_emits_an_event(): void
    {
        $component = new RecordingComponent('KontorCRM', '1.0.0');

        $this->manager->install($this->manifest('KontorCRM'), $component, $this->context());

        $this->assertTrue($this->registry->isEnabled('KontorCRM'));
        $this->assertTrue($component->registered);
        $this->assertTrue($component->booted);
        $this->assertSame('component.installed', $this->events->dispatched[0]->event);
    }

    public function test_install_fails_closed_when_dependencies_are_not_satisfied(): void
    {
        $manifest = $this->manifest('KontorCRM', requires: ['kontor/core' => '^0.1']);
        $component = new RecordingComponent('KontorCRM', '1.0.0');

        $this->expectException(DependencyCheckFailedException::class);

        try {
            $this->manager->install($manifest, $component, $this->context());
        } finally {
            $this->assertNull($this->registry->find('KontorCRM'));
            $this->assertFalse($component->registered);
        }
    }

    public function test_update_requires_component_to_already_be_installed(): void
    {
        $component = new RecordingComponent('KontorCRM', '1.1.0');

        $this->expectException(ComponentNotInstalledException::class);

        $this->manager->update($this->manifest('KontorCRM', '1.1.0'), $component, $this->context());
    }

    public function test_update_without_a_verified_backup_is_blocked(): void
    {
        $component = new RecordingComponent('KontorCRM', '1.0.0');
        $this->manager->install($this->manifest('KontorCRM'), $component, $this->context());

        $this->expectException(PreUpdateBackupRequiredException::class);

        $this->manager->update($this->manifest('KontorCRM', '1.1.0'), $component, $this->context());
    }

    public function test_update_proceeds_with_a_verified_backup(): void
    {
        $component = new RecordingComponent('KontorCRM', '1.0.0');
        $this->manager->install($this->manifest('KontorCRM'), $component, $this->context());

        $this->manager->update(
            $this->manifest('KontorCRM', '1.1.0'),
            $component,
            $this->context(),
            backupVerification: new BackupVerification(verified: true),
        );

        $this->assertSame('1.1.0', $this->registry->find('KontorCRM')['version']);
    }

    public function test_update_proceeds_when_backup_is_explicitly_bypassed(): void
    {
        $component = new RecordingComponent('KontorCRM', '1.0.0');
        $this->manager->install($this->manifest('KontorCRM'), $component, $this->context());

        $this->manager->update(
            $this->manifest('KontorCRM', '1.1.0'),
            $component,
            $this->context(),
            bypassBackup: true,
        );

        $this->assertSame('1.1.0', $this->registry->find('KontorCRM')['version']);
    }

    public function test_uninstall_retains_the_ledger_row(): void
    {
        $component = new RecordingComponent('KontorCRM', '1.0.0');
        $this->manager->install($this->manifest('KontorCRM'), $component, $this->context());

        $this->manager->uninstall('KontorCRM');

        $row = $this->registry->find('KontorCRM');
        $this->assertNotNull($row);
        $this->assertSame('uninstalled', $row['status']);
    }

    public function test_discover_reports_new_and_up_to_date_components(): void
    {
        $root = sys_get_temp_dir() . '/kontor-manager-discover-' . bin2hex(random_bytes(6));
        mkdir($root . '/KontorCRM', 0775, true);
        file_put_contents($root . '/KontorCRM/kontor.json', json_encode([
            'name' => 'KontorCRM', 'version' => '1.0.0', 'package' => 'kontor/crm',
            'namespace' => 'Kontor\\CRM', 'requires' => [],
        ], JSON_THROW_ON_ERROR));

        $manager = new ComponentManager(
            registry: $this->registry,
            discovery: new LocalDiscovery(),
            events: $this->events,
        );

        $discovered = $manager->discover($root);

        $this->assertCount(1, $discovered);
        $this->assertSame('new', $discovered[0]->status);

        $this->registry->markInstalled('KontorCRM', '1.0.0');
        $discovered = $manager->discover($root);
        $this->assertSame('up-to-date', $discovered[0]->status);

        $this->removeDirectory($root);
    }

    /**
     * @param array<string, string> $requires
     */
    private function manifest(string $name, string $version = '1.0.0', array $requires = []): ComponentManifest
    {
        return ComponentManifest::fromArray([
            'name' => $name,
            'version' => $version,
            'package' => 'kontor/' . strtolower($name),
            'namespace' => 'Kontor\\Test',
            'requires' => $requires,
        ]);
    }

    private function context(): ComponentContext
    {
        return new ComponentContext(
            container: new class implements ContainerInterface {
                public function get(string $id): mixed
                {
                    throw new \RuntimeException('not implemented');
                }

                public function has(string $id): bool
                {
                    return false;
                }
            },
            capabilities: new class implements \Kontor\SDK\Contracts\CapabilityRegistryInterface {
                public function register(string $capability, string $version, string $contract, object $implementation, string $component): void
                {
                }

                public function has(string $capability, ?string $constraint = null): bool
                {
                    return false;
                }

                public function get(string $capability, ?string $constraint = null): object
                {
                    throw new \RuntimeException('not implemented');
                }

                public function all(): array
                {
                    return [];
                }
            },
            events: $this->events,
            organizationId: 'org_test',
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

final class InMemoryComponentRegistry implements ComponentRegistryInterface
{
    /** @var array<string, array<string, mixed>> */
    private array $rows = [];

    public function markInstalled(string $name, string $version, ?string $source = null, ?string $checksum = null): void
    {
        $this->rows[$name] = [
            'name' => $name,
            'version' => $version,
            'status' => $this->rows[$name]['status'] ?? 'installed',
            'source' => $source,
            'checksum' => $checksum,
        ];
    }

    public function enable(string $name): void
    {
        $this->requireExists($name);
        $this->rows[$name]['status'] = 'enabled';
    }

    public function disable(string $name): void
    {
        $this->requireExists($name);
        $this->rows[$name]['status'] = 'disabled';
    }

    public function uninstall(string $name): void
    {
        $this->requireExists($name);
        $this->rows[$name]['status'] = 'uninstalled';
    }

    public function isEnabled(string $name): bool
    {
        return ($this->rows[$name]['status'] ?? null) === 'enabled';
    }

    public function find(string $name): ?array
    {
        return $this->rows[$name] ?? null;
    }

    public function all(): array
    {
        return array_values($this->rows);
    }

    private function requireExists(string $name): void
    {
        if (!isset($this->rows[$name])) {
            throw new \RuntimeException("Component \"{$name}\" is not registered.");
        }
    }
}

final class RecordingComponent implements ComponentInterface
{
    public bool $registered = false;
    public bool $booted = false;

    public function __construct(private readonly string $componentName, private readonly string $componentVersion)
    {
    }

    public function name(): string
    {
        return $this->componentName;
    }

    public function version(): string
    {
        return $this->componentVersion;
    }

    public function boot(ComponentContext $context): void
    {
        $this->booted = true;
    }

    public function register(ComponentContext $context): void
    {
        $this->registered = true;
    }

    public function healthChecks(): iterable
    {
        return [];
    }
}

final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<KontorEvent> */
    public array $dispatched = [];

    public function dispatch(KontorEvent $event): void
    {
        $this->dispatched[] = $event;
    }

    public function subscribe(string $eventName, callable|string $listener, int $priority = 0): void
    {
    }
}
