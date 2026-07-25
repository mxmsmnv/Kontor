<?php

declare(strict_types=1);

namespace Kontor\Contacts\Infrastructure\Persistence;

use Kontor\Contacts\Domain\ContactCompanyMembership;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

/**
 * kontor.md#12.4
 */
final class MembershipRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(ContactCompanyMembership $membership): void
    {
        $organizationId = $this->organizations->internalIdOf($membership->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_contact_company
                (organization_id, contact_uid, company_uid, role, department, is_primary, started_at, ended_at, metadata_json)
             VALUES
                (:organization_id, :contact_uid, :company_uid, :role, :department, :is_primary, :started_at, :ended_at, :metadata_json)
             ON DUPLICATE KEY UPDATE
                department = VALUES(department), is_primary = VALUES(is_primary),
                started_at = VALUES(started_at), ended_at = VALUES(ended_at), metadata_json = VALUES(metadata_json)'
        );

        $statement->execute([
            'organization_id' => $organizationId,
            'contact_uid' => $membership->contactUid,
            'company_uid' => $membership->companyUid,
            'role' => $membership->role,
            'department' => $membership->department,
            'is_primary' => $membership->isPrimary ? 1 : 0,
            'started_at' => $membership->startedAt?->format('Y-m-d'),
            'ended_at' => $membership->endedAt?->format('Y-m-d'),
            'metadata_json' => $membership->metadata !== [] ? json_encode($membership->metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function end(string $contactUid, string $companyUid, \DateTimeImmutable $endedAt): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE kontor_contact_company SET ended_at = :ended_at WHERE contact_uid = :contact_uid AND company_uid = :company_uid'
        );
        $statement->execute([
            'ended_at' => $endedAt->format('Y-m-d'),
            'contact_uid' => $contactUid,
            'company_uid' => $companyUid,
        ]);
    }

    /**
     * @return array<int, ContactCompanyMembership>
     */
    public function forContact(string $contactUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_contact_company WHERE contact_uid = :contact_uid');
        $statement->execute(['contact_uid' => $contactUid]);

        return array_map(fn (array $row): ContactCompanyMembership => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return array<int, ContactCompanyMembership>
     */
    public function forCompany(string $companyUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_contact_company WHERE company_uid = :company_uid');
        $statement->execute(['company_uid' => $companyUid]);

        return array_map(fn (array $row): ContactCompanyMembership => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): ContactCompanyMembership
    {
        return new ContactCompanyMembership(
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            contactUid: $row['contact_uid'],
            companyUid: $row['company_uid'],
            role: $row['role'],
            department: $row['department'],
            isPrimary: (bool) $row['is_primary'],
            startedAt: $row['started_at'] !== null ? new \DateTimeImmutable($row['started_at']) : null,
            endedAt: $row['ended_at'] !== null ? new \DateTimeImmutable($row['ended_at']) : null,
            metadata: $row['metadata_json'] !== null ? json_decode($row['metadata_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
