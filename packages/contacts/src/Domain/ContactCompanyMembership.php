<?php

declare(strict_types=1);

namespace Kontor\Contacts\Domain;

/**
 * kontor.md#12.4 — the spec's own schema has no uid/created_at columns
 * for this table; the domain object follows that exactly.
 */
final class ContactCompanyMembership
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $organizationId,
        public readonly string $contactUid,
        public readonly string $companyUid,
        public ?string $role,
        public ?string $department,
        public bool $isPrimary,
        public ?\DateTimeImmutable $startedAt,
        public ?\DateTimeImmutable $endedAt,
        public array $metadata = [],
    ) {
    }
}
