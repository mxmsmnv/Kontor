<?php

declare(strict_types=1);

namespace Kontor\Reports\Tests\Integration;

use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\Reports\Health\ReportsHealthCheck;
use Kontor\SDK\Contracts\ReportProviderInterface;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\ReportResult;
use Kontor\SDK\DTO\ReportSchema;

final class ReportsHealthCheckTest extends DatabaseTestCase
{
    public function test_warning_when_no_providers_are_registered(): void
    {
        $result = (new ReportsHealthCheck($this->pdo, new ReportProviderRegistry()))->run();

        $this->assertSame('warning', $result->status);
    }

    public function test_ok_when_providers_are_registered(): void
    {
        $registry = new ReportProviderRegistry();
        $registry->register(new class implements ReportProviderInterface {
            public function key(): string
            {
                return 'fake';
            }

            public function title(): string
            {
                return 'Fake';
            }

            public function schema(): ReportSchema
            {
                return new ReportSchema(fields: ['a' => 'string']);
            }

            public function execute(ReportQuery $query): ReportResult
            {
                return new ReportResult(rows: []);
            }
        });

        $result = (new ReportsHealthCheck($this->pdo, $registry))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['registeredProviders']);
    }
}
