<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\NavigationAvailability;
use PHPUnit\Framework\TestCase;

final class NavigationAvailabilityTest extends TestCase
{
    public function test_it_hides_routes_for_missing_optional_modules(): void
    {
        $routes = (new NavigationAvailability())->resolve(
            [
                ['url' => '', 'label' => 'Dashboard'],
                ['url' => 'crm/', 'label' => 'CRM', 'module' => 'KontorCRM'],
                ['url' => 'tasks/', 'label' => 'Tasks', 'module' => 'KontorTasks'],
            ],
            static fn (string $module): bool => $module === 'KontorTasks',
            static fn (string $permission): bool => true,
        );

        self::assertSame(['dashboard', 'tasks'], array_keys($routes));
    }

    public function test_it_hides_routes_without_permission(): void
    {
        $routes = (new NavigationAvailability())->resolve(
            [
                ['url' => 'contacts/', 'permission' => 'contacts-view'],
                ['url' => 'organization/', 'permission' => 'kontor-admin'],
            ],
            static fn (string $module): bool => true,
            static fn (string $permission): bool => $permission === 'contacts-view',
        );

        self::assertSame(['contacts'], array_keys($routes));
    }

    public function test_core_routes_need_neither_module_nor_permission(): void
    {
        $routes = (new NavigationAvailability())->resolve(
            [['url' => 'health/', 'label' => 'Health']],
            static fn (string $module): bool => false,
            static fn (string $permission): bool => false,
        );

        self::assertArrayHasKey('health', $routes);
    }
}
