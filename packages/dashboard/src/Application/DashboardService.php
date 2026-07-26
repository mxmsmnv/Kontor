<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Application;

use Kontor\Dashboard\Domain\Dashboard;
use Kontor\Dashboard\Domain\DashboardWidget;
use Kontor\Dashboard\Infrastructure\Persistence\DashboardRepository;
use Kontor\Dashboard\Infrastructure\Persistence\DashboardWidgetRepository;
use Kontor\Dashboard\Infrastructure\Registry\WidgetRegistry;
use Kontor\SDK\Contracts\CacheInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ties the "widget registry", "layouts", "personal dashboards" and "role
 * dashboards" milestones together: addWidget() validates its widgetKey is
 * actually registered before it can be placed on a layout, and
 * dashboardFor() resolves a user's personal default, falling back to
 * their role's default — the actual point of having both scopes.
 */
final class DashboardService
{
    public function __construct(
        private readonly DashboardRepository $dashboards,
        private readonly DashboardWidgetRepository $widgets,
        private readonly WidgetRegistry $widgetRegistry,
        private readonly ?CacheInterface $cache = null,
    ) {
    }

    public function createPersonalDashboard(string $organizationUid, int $ownerUserId, string $name, bool $isDefault = false, ?int $createdBy = null): Dashboard
    {
        if ($isDefault) {
            $this->clearExistingDefault($this->dashboards->forOwner($organizationUid, $ownerUserId));
        }

        $dashboard = Dashboard::personal($organizationUid, $ownerUserId, $name, $isDefault, $createdBy);
        $this->dashboards->save($dashboard);

        return $dashboard;
    }

    public function createRoleDashboard(string $organizationUid, string $role, string $name, bool $isDefault = false, ?int $createdBy = null): Dashboard
    {
        if ($isDefault) {
            $this->clearExistingDefault($this->dashboards->forRole($organizationUid, $role));
        }

        $dashboard = Dashboard::forRole($organizationUid, $role, $name, $isDefault, $createdBy);
        $this->dashboards->save($dashboard);

        return $dashboard;
    }

    /**
     * @param Dashboard[] $existing
     */
    private function clearExistingDefault(array $existing): void
    {
        foreach ($existing as $dashboard) {
            if ($dashboard->isDefault) {
                $dashboard->isDefault = false;
                $dashboard->updatedAt = new \DateTimeImmutable();
                $this->dashboards->save($dashboard);
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public function addWidget(
        string $organizationUid,
        string $dashboardUid,
        string $widgetKey,
        int $positionX = 0,
        int $positionY = 0,
        int $width = 4,
        int $height = 3,
        array $config = [],
        int $sortOrder = 0,
    ): DashboardWidget {
        if (!$this->widgetRegistry->has($widgetKey)) {
            throw new InvalidArgumentException("\"{$widgetKey}\" is not a registered widget.");
        }
        $dashboard = $this->dashboards->require($dashboardUid);
        if (!hash_equals($dashboard->organizationId, $organizationUid)) {
            throw new RuntimeException('Dashboard and widget must belong to the same organization.');
        }

        $widget = DashboardWidget::create($organizationUid, $dashboardUid, $widgetKey, $positionX, $positionY, $width, $height, $config, $sortOrder);
        $this->widgets->save($widget);
        $this->flushDashboard($dashboardUid);

        return $widget;
    }

    public function moveWidget(string $widgetUid, int $positionX, int $positionY): DashboardWidget
    {
        $widget = $this->widgets->require($widgetUid);
        $widget->positionX = $positionX;
        $widget->positionY = $positionY;
        $this->widgets->save($widget);
        $this->flushDashboard($widget->dashboardUid);

        return $widget;
    }

    public function resizeWidget(string $widgetUid, int $width, int $height): DashboardWidget
    {
        $widget = $this->widgets->require($widgetUid);
        $widget->width = $width;
        $widget->height = $height;
        $this->widgets->save($widget);
        $this->flushDashboard($widget->dashboardUid);

        return $widget;
    }

    public function removeWidget(string $widgetUid): void
    {
        $widget = $this->widgets->require($widgetUid);
        $this->widgets->remove($widgetUid);
        $this->flushDashboard($widget->dashboardUid);
    }

    /**
     * Resolves the dashboard a user should see: their personal default if
     * they have one, otherwise their role's default, otherwise null.
     */
    public function dashboardFor(string $organizationUid, int $userId, ?string $role = null): ?Dashboard
    {
        return $this->dashboards->defaultForOwner($organizationUid, $userId)
            ?? ($role !== null ? $this->dashboards->defaultForRole($organizationUid, $role) : null);
    }

    /**
     * Resolves the full dashboard: its layout, with each widget's key
     * paired with the data its provider renders.
     *
     * @return array{dashboard: Dashboard, widgets: array<int, array{layout: DashboardWidget, title: string, data: array<string, mixed>, cacheHit: bool}>}
     */
    public function render(string $dashboardUid, string $organizationUid, ?int $userId): array
    {
        $dashboard = $this->dashboards->require($dashboardUid);
        if (!hash_equals($dashboard->organizationId, $organizationUid)) {
            throw new RuntimeException('Dashboard does not belong to this organization.');
        }
        $rendered = [];

        foreach ($this->widgets->forDashboard($dashboardUid) as $layout) {
            $provider = $this->widgetRegistry->get($layout->widgetKey);
            $cacheKey = 'widget:' . hash('sha256', json_encode([
                $organizationUid,
                $userId ?? 0,
                $layout->uid->toString(),
                $layout->config,
            ], JSON_THROW_ON_ERROR));
            $tags = ['dashboard:' . $dashboardUid, 'widget:' . $layout->widgetKey];
            $data = $this->cache?->get($cacheKey, $tags);
            $cacheHit = is_array($data);
            if (!$cacheHit) {
                $data = $provider->render($organizationUid, $userId);
                $ttl = max(5, min(3600, (int) ($layout->config['refreshSeconds'] ?? 30)));
                $this->cache?->set($cacheKey, $data, $ttl, $tags);
            }
            $rendered[] = [
                'layout' => $layout,
                'title' => $provider->title(),
                'data' => $data,
                'cacheHit' => $cacheHit,
            ];
        }

        return ['dashboard' => $dashboard, 'widgets' => $rendered];
    }

    private function flushDashboard(string $dashboardUid): void
    {
        $this->cache?->flushTag('dashboard:' . $dashboardUid);
    }
}
