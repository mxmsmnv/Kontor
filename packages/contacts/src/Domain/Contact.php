<?php

declare(strict_types=1);

namespace Kontor\Contacts\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#12.1. $organizationId is the public organization uid
 * (kontor.md#10.3) — ContactRepository resolves it to the internal
 * organization_id BIGINT (kontor.md#10.4) at the persistence boundary,
 * the same pattern established for Files/Search in earlier stages.
 */
final class Contact
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $type,
        public ?string $firstName,
        public ?string $middleName,
        public ?string $lastName,
        public string $displayName,
        public ?string $email,
        public ?string $phone,
        public ?string $mobile,
        public ?string $jobTitle,
        public string $preferredLanguage,
        public ?string $preferredCurrency,
        public ?string $source,
        public string $status,
        public ?int $assignedUserId,
        public ?string $notes,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        ?string $firstName,
        ?string $middleName,
        ?string $lastName,
        ?string $displayName = null,
        string $type = 'individual',
        ?string $email = null,
        ?string $phone = null,
        ?string $mobile = null,
        ?string $jobTitle = null,
        string $preferredLanguage = 'en',
        ?string $preferredCurrency = null,
        ?string $source = null,
        string $status = 'active',
        ?int $assignedUserId = null,
        ?string $notes = null,
        array $metadata = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            type: $type,
            firstName: $firstName,
            middleName: $middleName,
            lastName: $lastName,
            displayName: $displayName ?? self::composeDisplayName($firstName, $middleName, $lastName),
            email: $email,
            phone: $phone,
            mobile: $mobile,
            jobTitle: $jobTitle,
            preferredLanguage: $preferredLanguage,
            preferredCurrency: $preferredCurrency,
            source: $source,
            status: $status,
            assignedUserId: $assignedUserId,
            notes: $notes,
            metadata: $metadata,
        );
    }

    public static function composeDisplayName(?string $firstName, ?string $middleName, ?string $lastName): string
    {
        return trim(implode(' ', array_filter([$firstName, $middleName, $lastName], static fn (?string $part): bool => $part !== null && $part !== '')));
    }
}
