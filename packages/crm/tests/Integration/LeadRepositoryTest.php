<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;

final class LeadRepositoryTest extends DatabaseTestCase
{
    private function repository(): LeadRepository
    {
        return new LeadRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips_including_money(): void
    {
        $repository = $this->repository();
        $lead = Lead::create($this->organizationUid, 'Big opportunity', contactUid: 'ct_01', estimatedValue: Money::ofMinor(500000, 'EUR'));

        $repository->save($lead);
        $found = $repository->find($lead->uid->toString());

        $this->assertSame('Big opportunity', $found->title);
        $this->assertSame('ct_01', $found->contactUid);
        $this->assertSame(500000, $found->estimatedValue->amountMinor());
    }

    public function test_require_throws_for_unknown_uid(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository()->require(\Kontor\SDK\ValueObjects\Uid::generate()->toString());
    }

    public function test_save_upserts_rather_than_duplicating(): void
    {
        $repository = $this->repository();
        $lead = Lead::create($this->organizationUid, 'Big opportunity');
        $repository->save($lead);

        $lead->status = 'qualified';
        $repository->save($lead);

        $this->assertSame('qualified', $repository->find($lead->uid->toString())->status);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_crm_leads')->fetchColumn());
    }

    public function test_archive_then_restore(): void
    {
        $repository = $this->repository();
        $lead = Lead::create($this->organizationUid, 'Big opportunity');
        $repository->save($lead);

        $repository->archive($lead->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_crm_leads')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['archived_at']);

        $repository->restore($lead->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_crm_leads')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['archived_at']);
    }

    public function test_for_organization_filters_by_status(): void
    {
        $repository = $this->repository();
        $repository->save(Lead::create($this->organizationUid, 'Lead A'));
        $qualified = Lead::create($this->organizationUid, 'Lead B');
        $qualified->status = 'qualified';
        $repository->save($qualified);

        $this->assertCount(2, $repository->forOrganization($this->organizationUid));
        $this->assertCount(1, $repository->forOrganization($this->organizationUid, 'qualified'));
    }

    public function test_matching_combines_search_status_archive_and_count(): void
    {
        $repository = $this->repository();
        $active = Lead::create($this->organizationUid, 'Website renewal', source: 'Referral');
        $active->status = 'qualified';
        $repository->save($active);
        $archived = Lead::create($this->organizationUid, 'Archived renewal', source: 'Referral');
        $archived->status = 'qualified';
        $repository->save($archived);
        $repository->archive($archived->uid->toString());

        $this->assertSame(
            ['Website renewal'],
            array_map(
                static fn (Lead $lead): string => $lead->title,
                $repository->findMatching($this->organizationUid, 'renewal', 'qualified')
            )
        );
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'renewal', 'qualified'));
        $this->assertSame(
            ['Archived renewal'],
            array_map(
                static fn (Lead $lead): string => $lead->title,
                $repository->findMatching($this->organizationUid, 'renewal', 'qualified', true)
            )
        );
    }
}
