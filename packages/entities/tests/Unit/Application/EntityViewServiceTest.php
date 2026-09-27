<?php

declare(strict_types=1);

namespace Kontor\Entities\Tests\Unit\Application;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Application\EntityViewService;
use Kontor\Entities\Domain\EntityRecord;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityViewRepository;
use PHPUnit\Framework\TestCase;

/**
 * filterRecords()/sortRecords() are pure — they only ever touch the
 * EntityRecord objects passed in, never the injected repositories — so
 * this needs no real database connection, just a PDO the service's
 * constructor is happy to hold onto without ever calling.
 */
final class EntityViewServiceTest extends TestCase
{
    private function service(): EntityViewService
    {
        $pdo = new class extends \PDO {
            public function __construct()
            {
            }
        };
        $organizations = new OrganizationRepository($pdo);

        return new EntityViewService(new EntityViewRepository($pdo, $organizations), new EntityRecordRepository($pdo, $organizations));
    }

    private function record(array $data): EntityRecord
    {
        return EntityRecord::create('org_01', 'def_01', $data);
    }

    public function test_filter_records_equals(): void
    {
        $records = [$this->record(['status' => 'open']), $this->record(['status' => 'closed'])];

        $filtered = $this->service()->filterRecords($records, [['field' => 'status', 'operator' => 'equals', 'value' => 'open']]);

        $this->assertCount(1, $filtered);
        $this->assertSame('open', $filtered[0]->data['status']);
    }

    public function test_filter_records_greater_than(): void
    {
        $records = [$this->record(['amount' => 5]), $this->record(['amount' => 50])];

        $filtered = $this->service()->filterRecords($records, [['field' => 'amount', 'operator' => 'greater_than', 'value' => 10]]);

        $this->assertCount(1, $filtered);
        $this->assertSame(50, $filtered[0]->data['amount']);
    }

    public function test_filter_records_with_multiple_filters_uses_and_semantics(): void
    {
        $records = [
            $this->record(['status' => 'open', 'amount' => 50]),
            $this->record(['status' => 'open', 'amount' => 5]),
            $this->record(['status' => 'closed', 'amount' => 50]),
        ];

        $filtered = $this->service()->filterRecords($records, [
            ['field' => 'status', 'operator' => 'equals', 'value' => 'open'],
            ['field' => 'amount', 'operator' => 'greater_than', 'value' => 10],
        ]);

        $this->assertCount(1, $filtered);
    }

    public function test_sort_records_ascending_and_descending(): void
    {
        $records = [$this->record(['amount' => 30]), $this->record(['amount' => 10]), $this->record(['amount' => 20])];

        $ascending = $this->service()->sortRecords($records, [['field' => 'amount', 'direction' => 'asc']]);
        $this->assertSame([10, 20, 30], array_map(fn ($r) => $r->data['amount'], $ascending));

        $descending = $this->service()->sortRecords($records, [['field' => 'amount', 'direction' => 'desc']]);
        $this->assertSame([30, 20, 10], array_map(fn ($r) => $r->data['amount'], $descending));
    }

    public function test_no_filters_returns_every_record(): void
    {
        $records = [$this->record(['a' => 1]), $this->record(['a' => 2])];

        $this->assertCount(2, $this->service()->filterRecords($records, []));
    }
}
