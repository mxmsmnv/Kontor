<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Unit\Application;

use Kontor\Mail\Infrastructure\Adapters\RawEmailForwardAdapter;
use Kontor\Mail\Infrastructure\Registry\InboundMailAdapterRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class InboundMailAdapterRegistryTest extends TestCase
{
    public function test_register_and_get(): void
    {
        $registry = new InboundMailAdapterRegistry();
        $adapter = new RawEmailForwardAdapter();

        $registry->register($adapter);

        $this->assertTrue($registry->has('forward'));
        $this->assertSame($adapter, $registry->get('forward'));
    }

    public function test_get_unknown_adapter_throws(): void
    {
        $registry = new InboundMailAdapterRegistry();

        $this->expectException(RuntimeException::class);

        $registry->get('missing');
    }

    public function test_all(): void
    {
        $registry = new InboundMailAdapterRegistry();
        $registry->register(new RawEmailForwardAdapter());

        $this->assertCount(1, $registry->all());
    }
}
