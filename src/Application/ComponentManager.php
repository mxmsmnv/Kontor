<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Domain\ComponentManifest;
use Kontor\Core\Domain\DiscoveredComponent;
use Kontor\Core\Infrastructure\Discovery\LocalDiscovery;
use Kontor\Core\Infrastructure\Registry\ComponentRegistryInterface;
use Kontor\SDK\Contracts\ComponentInterface;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\ComponentContext;
use Kontor\SDK\Events\KontorEvent;

/**
 * Orchestrates the component lifecycle (kontor.md Substage 1.3): local
 * discovery, dependency-checked install/update with a pre-update backup
 * gate, enable/disable, and uninstall with data retention (codex rule #10).
 */
final class ComponentManager
{
    /**
     * component.* events are instance-wide, not tenant-scoped (kontor_components
     * has no organization_id column at all) — the envelope still requires a
     * string organizationId (kontor.md#21), so a fixed sentinel is used.
     */
    private const SYSTEM_ORGANIZATION_EVENT_ID = 'system';

    /**
     * @param int|null $organizationId internal kontor_organizations.id (kontor.md#10.4) to
     *   attribute audit entries to; audit logging is skipped when null, since component
     *   lifecycle actions are not themselves organization-scoped.
     */
    public function __construct(
        private readonly ComponentRegistryInterface $registry,
        private readonly DependencyChecker $dependencyChecker = new DependencyChecker(),
        private readonly ?LocalDiscovery $discovery = null,
        private readonly ?EventDispatcherInterface $events = null,
        private readonly ?AuditLogger $audit = null,
        private readonly ?int $organizationId = null,
    ) {
    }

    /**
     * @param array<string, ComponentManifest> $installedManifests keyed by composer package name
     */
    public function install(
        ComponentManifest $manifest,
        ComponentInterface $component,
        ComponentContext $context,
        array $installedManifests = [],
        string $processWireVersion = '0.0.0',
        ?string $source = 'local',
        ?string $checksum = null,
        ?string $actorUid = null,
    ): void {
        $this->assertDependenciesSatisfied($manifest, $installedManifests, $processWireVersion);

        $this->registry->markInstalled($manifest->name, $manifest->version, $source, $checksum);
        $this->registry->enable($manifest->name);

        $component->register($context);
        $component->boot($context);

        $this->emit('component.installed', $manifest);
        $this->log('install', $manifest, $actorUid);
    }

    /**
     * @param array<string, ComponentManifest> $installedManifests keyed by composer package name
     */
    public function update(
        ComponentManifest $manifest,
        ComponentInterface $component,
        ComponentContext $context,
        array $installedManifests = [],
        string $processWireVersion = '0.0.0',
        ?BackupVerification $backupVerification = null,
        bool $bypassBackup = false,
        ?string $source = 'local',
        ?string $checksum = null,
        ?string $actorUid = null,
    ): void {
        if ($this->registry->find($manifest->name) === null) {
            throw new ComponentNotInstalledException(
                "\"{$manifest->name}\" is not installed. Use install() for a first-time install."
            );
        }

        if (!$bypassBackup && ($backupVerification === null || !$backupVerification->verified)) {
            throw new PreUpdateBackupRequiredException(
                "Updating \"{$manifest->name}\" requires a verified backup first (kontor.md#24). ".
                'Pass a verified BackupVerification, or bypassBackup: true for an actor holding '.
                'kontor-updates-bypass-backup.'
            );
        }

        $this->assertDependenciesSatisfied($manifest, $installedManifests, $processWireVersion);

        $this->registry->markInstalled($manifest->name, $manifest->version, $source, $checksum);

        $component->register($context);
        $component->boot($context);

        $this->emit('component.updated', $manifest);
        $this->log('update', $manifest, $actorUid);
    }

    public function enable(string $name, ?string $actorUid = null): void
    {
        $this->registry->enable($name);
        $this->emitByName('component.enabled', $name);
        $this->logByName('enable', $name, $actorUid);
    }

    public function disable(string $name, ?string $actorUid = null): void
    {
        $this->registry->disable($name);
        $this->emitByName('component.disabled', $name);
        $this->logByName('disable', $name, $actorUid);
    }

    /**
     * Marks the component uninstalled without deleting its ledger row or
     * touching any tables/directories it owns — ordinary uninstall must not
     * remove user data (codex rule #10).
     */
    public function uninstall(string $name, ?string $actorUid = null): void
    {
        if ($this->registry->isEnabled($name)) {
            $this->registry->disable($name);
        }

        $this->registry->uninstall($name);
        $this->emitByName('component.uninstalled', $name);
        $this->logByName('uninstall', $name, $actorUid);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function installed(): array
    {
        return $this->registry->all();
    }

    /**
     * @return array<int, DiscoveredComponent>
     */
    public function discover(string $rootDir): array
    {
        $discovery = $this->discovery ?? new LocalDiscovery();
        $installed = [];

        foreach ($this->registry->all() as $row) {
            $installed[$row['name']] = $row['version'];
        }

        return array_map(
            function (ComponentManifest $manifest) use ($installed): DiscoveredComponent {
                $installedVersion = $installed[$manifest->name] ?? null;

                $status = match (true) {
                    $installedVersion === null => 'new',
                    $installedVersion !== $manifest->version => 'update-available',
                    default => 'up-to-date',
                };

                return new DiscoveredComponent($manifest, $status, $installedVersion);
            },
            $discovery->scan($rootDir)
        );
    }

    /**
     * @param array<string, ComponentManifest> $installedManifests
     */
    private function assertDependenciesSatisfied(
        ComponentManifest $manifest,
        array $installedManifests,
        string $processWireVersion,
    ): void {
        $result = $this->dependencyChecker->check($manifest, $installedManifests, PHP_VERSION, $processWireVersion);

        if (!$result->satisfied) {
            throw new DependencyCheckFailedException($result);
        }
    }

    private function emit(string $eventName, ComponentManifest $manifest): void
    {
        $this->emitByName($eventName, $manifest->name, ['version' => $manifest->version]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emitByName(string $eventName, string $componentName, array $data = []): void
    {
        $this->events?->dispatch(KontorEvent::create(
            event: $eventName,
            organizationId: self::SYSTEM_ORGANIZATION_EVENT_ID,
            entityType: 'component',
            entityId: $componentName,
            actorType: 'system',
            actorId: null,
            data: ['name' => $componentName, ...$data],
        ));
    }

    private function log(string $action, ComponentManifest $manifest, ?string $actorUid): void
    {
        $this->logByName($action, $manifest->name, $actorUid, ['version' => $manifest->version]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function logByName(string $action, string $componentName, ?string $actorUid, array $metadata = []): void
    {
        if ($this->organizationId === null) {
            return;
        }

        $this->audit?->record(
            organizationId: $this->organizationId,
            component: 'core',
            entityType: 'component',
            entityUid: $componentName,
            action: $action,
            actorType: $actorUid !== null ? 'user' : 'system',
            actorUid: $actorUid,
            metadata: $metadata,
        );
    }
}
