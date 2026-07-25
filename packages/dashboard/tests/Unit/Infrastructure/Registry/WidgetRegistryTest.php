<?php

declare(strict_types=1);

namespace Kontor\Dashboard\Tests\Unit\Infrastructure\Registry;

use Kontor\Dashboard\Contracts\WidgetProviderInterface;
use Kontor\Dashboard\Infrastructure\Registry\WidgetRegistry;
use PHPUnit\Framework\TestCase;

final class WidgetRegistryTest extends TestCase
{
    private function widget(string $key): WidgetProviderInterface
    {
        return new class($key) implements WidgetProviderInterface {
            public function __construct(private readonly string $key)
            {
            }

            public function key(): string
            {
                return $this->key;
            }

            public function title(): string
            {
                return ucfirst($this->key);
            }

            public function render(string $organizationUid, ?int $userId): array
            {
                return [];
            }
        };
    }

    public function test_register_then_get(): void
    {
        $registry = new WidgetRegistry();
        $registry->register($this->widget('clock'));

        $this->assertTrue($registry->has('clock'));
        $this->assertSame('Clock', $registry->get('clock')->title());
    }

    public function test_get_throws_for_an_unregistered_key(): void
    {
        $this->expectException(\RuntimeException::class);

        (new WidgetRegistry())->get('missing');
    }

    public function test_all_returns_every_registered_provider_keyed_by_key(): void
    {
        $registry = new WidgetRegistry();
        $registry->register($this->widget('clock'));
        $registry->register($this->widget('tasks'));

        $this->assertSame(['clock', 'tasks'], array_keys($registry->all()));
    }
}
