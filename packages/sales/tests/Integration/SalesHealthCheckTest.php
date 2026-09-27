<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Sales\Domain\Quotation;
use Kontor\Sales\Health\SalesHealthCheck;
use Kontor\Sales\Infrastructure\Persistence\QuotationRepository;

final class SalesHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_counts(): void
    {
        (new QuotationRepository($this->pdo, new OrganizationRepository($this->pdo)))
            ->save(Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR'));

        $result = (new SalesHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['quotations']);
        $this->assertSame(0, $result->details['openOrders']);
    }
}
