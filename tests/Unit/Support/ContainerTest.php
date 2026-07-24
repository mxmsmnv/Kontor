<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Support;

use Kontor\Core\Support\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

final class ContainerTest extends TestCase
{
    public function test_bind_resolves_lazily_and_caches_the_instance(): void
    {
        $container = new Container();
        $calls = 0;

        $container->bind('service', function () use (&$calls) {
            $calls++;

            return new \stdClass();
        });

        $first = $container->get('service');
        $second = $container->get('service');

        $this->assertSame($first, $second);
        $this->assertSame(1, $calls);
    }

    public function test_instance_registers_an_already_built_object(): void
    {
        $container = new Container();
        $object = new \stdClass();

        $container->instance('service', $object);

        $this->assertTrue($container->has('service'));
        $this->assertSame($object, $container->get('service'));
    }

    public function test_get_throws_not_found_for_unbound_service(): void
    {
        $container = new Container();

        $this->expectException(NotFoundExceptionInterface::class);

        $container->get('missing');
    }

    public function test_get_wraps_factory_exceptions(): void
    {
        $container = new Container();
        $container->bind('broken', function () {
            throw new \RuntimeException('boom');
        });

        $this->expectException(ContainerExceptionInterface::class);

        $container->get('broken');
    }

    public function test_has_reflects_both_factories_and_instances(): void
    {
        $container = new Container();

        $this->assertFalse($container->has('service'));

        $container->bind('service', fn () => new \stdClass());

        $this->assertTrue($container->has('service'));
    }
}
