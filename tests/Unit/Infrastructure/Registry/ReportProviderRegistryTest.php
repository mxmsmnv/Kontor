<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Registry;

use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\SDK\Contracts\ReportProviderInterface;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\ReportResult;
use Kontor\SDK\DTO\ReportSchema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ReportProviderRegistryTest extends TestCase
{
    public function test_register_then_get(): void
    {
        $registry = new ReportProviderRegistry();
        $provider = new FakeReportProvider('crm_pipeline');

        $registry->register($provider);

        $this->assertTrue($registry->has('crm_pipeline'));
        $this->assertSame($provider, $registry->get('crm_pipeline'));
    }

    public function test_get_throws_for_unknown_key(): void
    {
        $this->expectException(RuntimeException::class);

        (new ReportProviderRegistry())->get('unknown');
    }

    public function test_all_returns_every_registered_provider_keyed_by_key(): void
    {
        $registry = new ReportProviderRegistry();
        $provider = new FakeReportProvider('crm_pipeline');
        $registry->register($provider);

        $this->assertSame(['crm_pipeline' => $provider], $registry->all());
    }
}

final class FakeReportProvider implements ReportProviderInterface
{
    public function __construct(private readonly string $reportKey)
    {
    }

    public function key(): string
    {
        return $this->reportKey;
    }

    public function title(): string
    {
        return 'Fake report';
    }

    public function schema(): ReportSchema
    {
        return new ReportSchema([]);
    }

    public function execute(ReportQuery $query): ReportResult
    {
        return new ReportResult([]);
    }
}
