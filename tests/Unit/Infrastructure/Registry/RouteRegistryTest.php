<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Registry;

use Kontor\Core\Infrastructure\Registry\RouteRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouteRegistryTest extends TestCase
{
    public function test_register_and_match(): void
    {
        $routes = new RouteRegistry();
        $routes->register('crm/deals', 'Kontor\\CRM\\Admin\\DealsController', 'KontorCRM');

        $match = $routes->match('/crm/deals/');

        $this->assertSame('Kontor\\CRM\\Admin\\DealsController', $match['controller']);
        $this->assertSame('KontorCRM', $match['component']);
    }

    public function test_match_returns_null_for_unknown_path(): void
    {
        $routes = new RouteRegistry();

        $this->assertNull($routes->match('crm/deals'));
    }

    public function test_registering_the_same_path_twice_throws(): void
    {
        $routes = new RouteRegistry();
        $routes->register('crm/deals', 'ControllerA', 'KontorCRM');

        $this->expectException(RuntimeException::class);

        $routes->register('crm/deals', 'ControllerB', 'KontorSales');
    }
}
