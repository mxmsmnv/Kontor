<?php

declare(strict_types=1);

namespace Kontor\Queue\Tests\Unit;

use Kontor\Queue\JobRegistry;
use Kontor\Queue\Tests\Support\FakeJob;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JobRegistryTest extends TestCase
{
    public function test_register_and_make(): void
    {
        $registry = new JobRegistry();
        $registry->register('fake.job', static fn (array $payload): FakeJob => new FakeJob($payload));

        $this->assertTrue($registry->has('fake.job'));
        $job = $registry->make('fake.job', ['n' => 5]);

        $this->assertInstanceOf(FakeJob::class, $job);
        $this->assertSame(['n' => 5], $job->payload());
    }

    public function test_make_throws_for_unregistered_job_type(): void
    {
        $this->expectException(RuntimeException::class);

        (new JobRegistry())->make('unknown.job', []);
    }

    public function test_has_returns_false_for_unregistered_job_type(): void
    {
        $this->assertFalse((new JobRegistry())->has('unknown.job'));
    }
}
