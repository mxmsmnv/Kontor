<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Dashboard\Application\DashboardService;
use Kontor\Dashboard\Health\DashboardHealthCheck;
use Kontor\Dashboard\Infrastructure\Persistence\DashboardRepository;
use Kontor\Dashboard\Infrastructure\Persistence\DashboardWidgetRepository;
use Kontor\Dashboard\Infrastructure\Registry\WidgetRegistry;
use Kontor\Dashboard\Migrations\Migration0001CreateDashboardsTable;
use Kontor\Dashboard\Migrations\Migration0002CreateDashboardWidgetsTable;
use Kontor\Dashboard\Widgets\WelcomeWidgetProvider;

/**
 * KontorDashboard bootstrap module (kontor.md Substage 5.3). Depends only
 * on kontor/core. widgetRegistry() is exposed so other components can
 * fetch it and register their own WidgetProviderInterface implementations
 * during their own module init (mirroring how kontor/search's
 * SearchProviderRegistry is reached the same way) — none register in this
 * substage; see the README.
 */
class KontorDashboard extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Dashboard',
            'summary' => 'Widget registry, layouts, personal dashboards, role dashboards.',
            'version' => '002',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorDashboard',
            'icon' => 'th-large',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-dashboard-view' => 'View dashboards',
                'kontor-dashboard-create' => 'Create dashboards',
                'kontor-dashboard-edit' => 'Edit dashboards and their layout',
                'kontor-dashboard-archive' => 'Archive dashboards',
                'kontor-dashboard-role-manage' => 'Manage role dashboards',
            ],
        ];
    }

    private ?DashboardRepository $dashboardRepository = null;
    private ?DashboardWidgetRepository $widgetRepository = null;
    private ?WidgetRegistry $widgetRegistry = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
        $this->widgetRegistry()->register(new WelcomeWidgetProvider());
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorDashboard', $language, $strings);
        }
    }

    public function dashboardRepository(): DashboardRepository
    {
        return $this->dashboardRepository ??= new DashboardRepository($this->pdo(), $this->organizations());
    }

    public function widgetRepository(): DashboardWidgetRepository
    {
        return $this->widgetRepository ??= new DashboardWidgetRepository($this->pdo(), $this->organizations());
    }

    public function widgetRegistry(): WidgetRegistry
    {
        return $this->widgetRegistry ??= new WidgetRegistry();
    }

    public function dashboardService(): DashboardService
    {
        return new DashboardService($this->dashboardRepository(), $this->widgetRepository(), $this->widgetRegistry());
    }

    public function healthCheck(): DashboardHealthCheck
    {
        return new DashboardHealthCheck($this->pdo(), $this->widgetRegistry());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

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
            new Migration0001CreateDashboardsTable(),
            new Migration0002CreateDashboardWidgetsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('dashboard', self::getModuleInfo()['version'], 'dashboard');
        $components->enable('dashboard');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('dashboard', self::getModuleInfo()['version'], 'dashboard');
        $components->enable('dashboard');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Dashboard module removed. Dashboard data was kept intact.'));
    }
}
