<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Dashboard\Application\DashboardService;
use Kontor\Dashboard\Health\DashboardHealthCheck;
use Kontor\Dashboard\Infrastructure\Persistence\DashboardRepository;
use Kontor\Dashboard\Infrastructure\Persistence\DashboardWidgetRepository;
use Kontor\Dashboard\Infrastructure\Registry\WidgetRegistry;
use Kontor\Dashboard\Widgets\WelcomeWidgetProvider;

final class DashboardHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_when_widgets_are_registered(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $service = new DashboardService(
            new DashboardRepository($this->pdo, $organizations),
            new DashboardWidgetRepository($this->pdo, $organizations),
            new WidgetRegistry(),
        );
        $service->createPersonalDashboard($this->organizationUid, 7, 'My dashboard');

        $registry = new WidgetRegistry();
        $registry->register(new WelcomeWidgetProvider());

        $result = (new DashboardHealthCheck($this->pdo, $registry))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['dashboards']);
        $this->assertSame(1, $result->details['registeredWidgets']);
    }

    public function test_warning_when_no_widgets_are_registered(): void
    {
        $result = (new DashboardHealthCheck($this->pdo, new WidgetRegistry()))->run();

        $this->assertSame('warning', $result->status);
    }
}
