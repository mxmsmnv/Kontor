<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Settings\Application\SettingsMigrationService;
use Kontor\Settings\Application\SettingsProviderRegistry;
use Kontor\Settings\Contracts\SettingsMigrationInterface;
use Kontor\Settings\Health\SettingsHealthCheck;
use Kontor\Settings\Infrastructure\Provider\OrganizationSettingsProvider;

class KontorSettings extends WireData implements Module
{
    private ?SettingsProviderRegistry $providers = null;
    private ?SettingsMigrationService $migration = null;

    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Settings',
            'summary' => 'Safe, versioned export and import of portable workspace settings.',
            'version' => '100',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorSettings',
            'icon' => 'exchange',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-settings-export' => 'Export portable Kontor settings',
                'kontor-settings-import' => 'Preview and import portable Kontor settings',
            ],
        ];
    }

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        $kontor->container()->get(CapabilityRegistry::class)->register(
            capability: 'settings-migration',
            version: '1.0',
            contract: SettingsMigrationInterface::class,
            implementation: $this->migrationService(),
            component: 'KontorSettings',
        );
    }

    public function providerRegistry(): SettingsProviderRegistry
    {
        if ($this->providers !== null) {
            return $this->providers;
        }

        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        $organizations = $kontor->container()->get(OrganizationRepository::class);
        $organization = $organizations->defaultOrganization('US', 'en', 'USD');
        $registry = new SettingsProviderRegistry();
        $registry->register(new OrganizationSettingsProvider(
            $organizations,
            $organization->uid->toString(),
        ));

        return $this->providers = $registry;
    }

    public function migrationService(): SettingsMigrationService
    {
        return $this->migration ??= new SettingsMigrationService($this->providerRegistry());
    }

    public function healthCheck(): SettingsHealthCheck
    {
        return new SettingsHealthCheck($this->providerRegistry());
    }

    public function ___install(): void
    {
        $components = new ComponentRegistry(\Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database));
        $components->markInstalled('settings', self::getModuleInfo()['version'], 'settings');
        $components->enable('settings');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        foreach (self::getModuleInfo()['permissions'] as $name => $title) {
            $permission = $this->wire()->permissions->get($name);
            if ($permission->id) {
                continue;
            }
            $permission = $this->wire()->permissions->add($name);
            $permission->title = $title;
            $this->wire()->permissions->save($permission);
        }

        $components = new ComponentRegistry(\Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database));
        $components->markInstalled('settings', self::getModuleInfo()['version'], 'settings');
        $components->enable('settings');
    }

    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Settings module removed. Existing workspace settings were not changed.'));
    }
}
