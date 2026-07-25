<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Dashboard\Application\DashboardService;
use Kontor\Dashboard\Infrastructure\Persistence\DashboardRepository;
use Kontor\Dashboard\Infrastructure\Persistence\DashboardWidgetRepository;
use Kontor\Dashboard\Infrastructure\Registry\WidgetRegistry;
use Kontor\Dashboard\Widgets\WelcomeWidgetProvider;

final class DashboardServiceTest extends DatabaseTestCase
{
    private DashboardService $service;
    private DashboardRepository $dashboards;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->dashboards = new DashboardRepository($this->pdo, $organizations);
        $widgets = new DashboardWidgetRepository($this->pdo, $organizations);
        $registry = new WidgetRegistry();
        $registry->register(new WelcomeWidgetProvider());

        $this->service = new DashboardService($this->dashboards, $widgets, $registry);
    }

    public function test_creating_a_second_default_personal_dashboard_unsets_the_first(): void
    {
        $first = $this->service->createPersonalDashboard($this->organizationUid, 7, 'My first', isDefault: true);
        $second = $this->service->createPersonalDashboard($this->organizationUid, 7, 'My second', isDefault: true);

        $this->assertFalse($this->dashboards->require($first->uid->toString())->isDefault);
        $this->assertTrue($this->dashboards->require($second->uid->toString())->isDefault);
    }

    public function test_role_dashboards_default_swap_independently_of_personal_ones(): void
    {
        $this->service->createPersonalDashboard($this->organizationUid, 7, 'Personal', isDefault: true);
        $roleDashboard = $this->service->createRoleDashboard($this->organizationUid, 'sales-rep', 'Sales team', isDefault: true);

        $this->assertTrue($this->dashboards->require($roleDashboard->uid->toString())->isDefault);
        $this->assertCount(1, $this->dashboards->forOwner($this->organizationUid, 7));
    }

    public function test_add_widget_rejects_an_unregistered_widget_key(): void
    {
        $dashboard = $this->service->createPersonalDashboard($this->organizationUid, 7, 'My dashboard');

        $this->expectException(\InvalidArgumentException::class);
        $this->service->addWidget($this->organizationUid, $dashboard->uid->toString(), 'not-a-real-widget');
    }

    public function test_add_move_and_resize_a_widget(): void
    {
        $dashboard = $this->service->createPersonalDashboard($this->organizationUid, 7, 'My dashboard');
        $widget = $this->service->addWidget($this->organizationUid, $dashboard->uid->toString(), 'welcome', positionX: 0, positionY: 0);

        $moved = $this->service->moveWidget($widget->uid->toString(), 2, 3);
        $this->assertSame(2, $moved->positionX);
        $this->assertSame(3, $moved->positionY);

        $resized = $this->service->resizeWidget($widget->uid->toString(), 6, 4);
        $this->assertSame(6, $resized->width);
        $this->assertSame(4, $resized->height);
    }

    public function test_dashboard_for_prefers_personal_default_over_role_default(): void
    {
        $this->service->createRoleDashboard($this->organizationUid, 'sales-rep', 'Team default', isDefault: true);
        $personal = $this->service->createPersonalDashboard($this->organizationUid, 7, 'My default', isDefault: true);

        $resolved = $this->service->dashboardFor($this->organizationUid, 7, 'sales-rep');

        $this->assertSame($personal->uid->toString(), $resolved->uid->toString());
    }

    public function test_dashboard_for_falls_back_to_role_default_with_no_personal_default(): void
    {
        $roleDashboard = $this->service->createRoleDashboard($this->organizationUid, 'sales-rep', 'Team default', isDefault: true);

        $resolved = $this->service->dashboardFor($this->organizationUid, 7, 'sales-rep');

        $this->assertSame($roleDashboard->uid->toString(), $resolved->uid->toString());
    }

    public function test_dashboard_for_returns_null_when_nothing_matches(): void
    {
        $this->assertNull($this->service->dashboardFor($this->organizationUid, 7, null));
    }

    public function test_render_resolves_layout_and_widget_data_together(): void
    {
        $dashboard = $this->service->createPersonalDashboard($this->organizationUid, 7, 'My dashboard');
        $this->service->addWidget($this->organizationUid, $dashboard->uid->toString(), 'welcome');

        $result = $this->service->render($dashboard->uid->toString(), $this->organizationUid, 7);

        $this->assertSame($dashboard->uid->toString(), $result['dashboard']->uid->toString());
        $this->assertCount(1, $result['widgets']);
        $this->assertSame('Welcome', $result['widgets'][0]['title']);
        $this->assertSame($this->organizationUid, $result['widgets'][0]['data']['organizationUid']);
        $this->assertSame(7, $result['widgets'][0]['data']['userId']);
    }
}
