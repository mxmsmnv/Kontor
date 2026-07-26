<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Domain\ContactCompanyMembership;
use Kontor\Contacts\Infrastructure\Persistence\MembershipRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class MembershipRepositoryTest extends DatabaseTestCase
{
    private function repository(): MembershipRepository
    {
        return new MembershipRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_for_contact(): void
    {
        $repository = $this->repository();
        $membership = new ContactCompanyMembership(
            organizationId: $this->organizationUid,
            contactUid: 'ct_01',
            companyUid: 'cmp_01',
            role: 'Engineer',
            department: 'R&D',
            isPrimary: true,
            startedAt: new \DateTimeImmutable('2024-01-01'),
            endedAt: null,
        );

        $repository->save($membership);
        $memberships = $repository->forContact('ct_01');

        $this->assertCount(1, $memberships);
        $this->assertSame('Engineer', $memberships[0]->role);
        $this->assertSame('cmp_01', $memberships[0]->companyUid);
        $this->assertEquals(new \DateTimeImmutable('2024-01-01'), $memberships[0]->startedAt);
    }

    public function test_for_company(): void
    {
        $repository = $this->repository();
        $repository->save(new ContactCompanyMembership(
            $this->organizationUid, 'ct_01', 'cmp_01', 'Engineer', null, false, null, null,
        ));
        $repository->save(new ContactCompanyMembership(
            $this->organizationUid, 'ct_02', 'cmp_01', 'Manager', null, true, null, null,
        ));

        $this->assertCount(2, $repository->forCompany('cmp_01'));
    }

    public function test_end_sets_the_end_date(): void
    {
        $repository = $this->repository();
        $repository->save(new ContactCompanyMembership(
            $this->organizationUid, 'ct_01', 'cmp_01', 'Engineer', null, false, null, null,
        ));

        $repository->end('ct_01', 'cmp_01', new \DateTimeImmutable('2026-06-01'));

        $memberships = $repository->forContact('ct_01');
        $this->assertEquals(new \DateTimeImmutable('2026-06-01'), $memberships[0]->endedAt);
    }

    public function test_end_does_not_rewrite_historical_memberships(): void
    {
        $repository = $this->repository();
        $repository->save(new ContactCompanyMembership(
            $this->organizationUid,
            'ct_01',
            'cmp_01',
            'Former role',
            null,
            false,
            null,
            new \DateTimeImmutable('2025-01-01'),
        ));

        $repository->end('ct_01', 'cmp_01', new \DateTimeImmutable('2026-06-01'));

        $this->assertEquals(
            new \DateTimeImmutable('2025-01-01'),
            $repository->forContact('ct_01')[0]->endedAt
        );
    }

    public function test_same_role_upserts_rather_than_duplicating(): void
    {
        $repository = $this->repository();
        $repository->save(new ContactCompanyMembership($this->organizationUid, 'ct_01', 'cmp_01', 'Engineer', 'R&D', false, null, null));
        $repository->save(new ContactCompanyMembership($this->organizationUid, 'ct_01', 'cmp_01', 'Engineer', 'Platform', true, null, null));

        $memberships = $repository->forContact('ct_01');
        $this->assertCount(1, $memberships);
        $this->assertSame('Platform', $memberships[0]->department);
    }
}
