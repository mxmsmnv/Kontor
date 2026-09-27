<?php

declare(strict_types=1);

namespace Kontor\Reports\Tests\Unit\Application;

use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\Reports\Application\ReportBuilderService;
use Kontor\SDK\Contracts\ReportProviderInterface;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\ReportResult;
use Kontor\SDK\DTO\ReportSchema;
use PHPUnit\Framework\TestCase;

final class ReportBuilderServiceTest extends TestCase
{
    private function fakeProvider(): ReportProviderInterface
    {
        return new class implements ReportProviderInterface {
            public function key(): string
            {
                return 'fake';
            }

            public function title(): string
            {
                return 'Fake report';
            }

            public function schema(): ReportSchema
            {
                return new ReportSchema(
                    fields: ['status' => 'string', 'count' => 'int'],
                    filterableFields: ['status'],
                    groupableFields: ['status'],
                );
            }

            public function execute(ReportQuery $query): ReportResult
            {
                return new ReportResult(rows: [['status' => 'open', 'count' => 3]]);
            }
        };
    }

    public function test_runs_a_query_with_only_valid_filters_and_group_by(): void
    {
        $registry = new ReportProviderRegistry();
        $registry->register($this->fakeProvider());
        $builder = new ReportBuilderService($registry);

        $result = $builder->run('fake', new ReportQuery('org_01', filters: ['status' => 'open'], groupBy: ['status']));

        $this->assertSame([['status' => 'open', 'count' => 3]], $result->rows);
    }

    public function test_rejects_a_filter_field_the_provider_does_not_declare_as_filterable(): void
    {
        $registry = new ReportProviderRegistry();
        $registry->register($this->fakeProvider());
        $builder = new ReportBuilderService($registry);

        $this->expectException(\InvalidArgumentException::class);
        $builder->run('fake', new ReportQuery('org_01', filters: ['not_a_field' => 'x']));
    }

    public function test_rejects_a_group_by_field_the_provider_does_not_declare_as_groupable(): void
    {
        $registry = new ReportProviderRegistry();
        $registry->register($this->fakeProvider());
        $builder = new ReportBuilderService($registry);

        $this->expectException(\InvalidArgumentException::class);
        $builder->run('fake', new ReportQuery('org_01', groupBy: ['count']));
    }

    public function test_unknown_provider_key_throws(): void
    {
        $builder = new ReportBuilderService(new ReportProviderRegistry());

        $this->expectException(\RuntimeException::class);
        $builder->run('missing', new ReportQuery('org_01'));
    }

    public function test_validate_for_checks_fields_without_executing(): void
    {
        $registry = new ReportProviderRegistry();
        $registry->register($this->fakeProvider());
        $builder = new ReportBuilderService($registry);

        // No exception means it passed validation without needing a query.
        $builder->validateFor('fake', ['status' => 'open'], ['status']);

        $this->expectException(\InvalidArgumentException::class);
        $builder->validateFor('fake', ['not_a_field' => 'x'], []);
    }
}
