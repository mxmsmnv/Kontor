<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Health\CRMHealthCheck;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class CRMHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_counts(): void
    {
        (new LeadRepository($this->pdo, new OrganizationRepository($this->pdo)))
            ->save(Lead::create($this->organizationUid, 'Big opportunity'));

        $result = (new CRMHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['leads']);
        $this->assertSame(0, $result->details['openDeals']);
    }
}
