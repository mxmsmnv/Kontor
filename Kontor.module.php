<?php

namespace ProcessWire;

use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Application\ComponentManager;
use Kontor\Core\Infrastructure\Events\EventDispatcher;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\RouteRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Migrations\Migration0002CreateComponentsTable;
use Kontor\Core\Migrations\Migration0003CreateAuditEventsTable;
use Kontor\Core\Migrations\Migration0004CreateSequencesTable;
use Kontor\Core\Migrations\Migration0005CreateExtensionsTable;
use Kontor\Core\Migrations\Migration0006CreateRelationsTable;
use Kontor\Core\Support\Container;

/**
 * Kontor Core bootstrap module (kontor.md Substage 1.1). Boots the service
 * container and the core registries; business components depend on this
 * module and register themselves into it, they never get their own
 * standalone bootstrap.
 */
class Kontor extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor',
            'summary' => 'Open-source modular ERP, CRM and business operations platform.',
            'version' => '001',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/Kontor',
            'icon' => 'cubes',
            'singular' => true,
            'autoload' => true,
            'requires' => ['ProcessWire>=3.0.240'],
            'installs' => ['ProcessKontor'],
            'permissions' => [
                'kontor-access' => 'Access the Kontor admin',
                'kontor-admin' => 'Administer Kontor settings and components',
                'kontor-components-view' => 'View installed Kontor components',
                'kontor-components-install' => 'Install Kontor components',
                'kontor-components-update' => 'Update Kontor components',
                'kontor-components-disable' => 'Disable Kontor components',
                'kontor-components-remove' => 'Remove Kontor components',
                'kontor-audit-view' => 'View the Kontor audit log',
                'kontor-health-view' => 'View Kontor health checks',
            ],
        ];
    }

    private ?Container $container = null;

    public function init(): void
    {
        $this->container();
    }

    /**
     * The shared service container. Business components receive the
     * services they need via ComponentContext rather than pulling this
     * directly (kontor.md#9.1), but the container itself lives here.
     */
    public function container(): Container
    {
        if ($this->container !== null) {
            return $this->container;
        }

        $container = new Container();
        $pdo = $this->pdo();

        $container->instance(\PDO::class, $pdo);
        $container->bind(CapabilityRegistry::class, static fn (): CapabilityRegistry => new CapabilityRegistry());
        $container->bind(EventDispatcher::class, static fn (): EventDispatcher => new EventDispatcher());
        $container->bind(RouteRegistry::class, static fn (): RouteRegistry => new RouteRegistry());
        $container->bind(TranslationRegistry::class, static fn (): TranslationRegistry => new TranslationRegistry());
        $container->bind(ComponentRegistry::class, static fn (): ComponentRegistry => new ComponentRegistry($pdo));
        $container->bind(OrganizationRepository::class, static fn (): OrganizationRepository => new OrganizationRepository($pdo));
        $container->bind(AuditLogger::class, static fn (): AuditLogger => new AuditLogger($pdo));
        $container->bind(
            ComponentManager::class,
            static fn (Container $c): ComponentManager => new ComponentManager($c->get(ComponentRegistry::class))
        );

        return $this->container = $container;
    }

    /**
     * ProcessWire's $database is a WireDatabasePDO wrapper; Kontor's
     * persistence layer talks to the underlying \PDO directly so it stays
     * portable outside ProcessWire (kontor.md#8.3).
     */
    private function pdo(): \PDO
    {
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateOrganizationsTable(),
            new Migration0002CreateComponentsTable(),
            new Migration0003CreateAuditEventsTable(),
            new Migration0004CreateSequencesTable(),
            new Migration0005CreateExtensionsTable(),
            new Migration0006CreateRelationsTable(),
        ]);

        (new OrganizationRepository($pdo))->defaultOrganization('US', 'en', 'EUR');

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('core', self::getModuleInfo()['version'], 'core');
        $components->enable('core');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data, so the
     * business tables created by ___install() are left in place.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Core module removed. Business data tables were kept intact.'));
    }
}
