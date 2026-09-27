<?php

declare(strict_types=1);

namespace Kontor\Contacts\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#12.3 — polymorphic owner (owner_type/owner_uid), so the same
 * table serves both contacts and companies.
 */
final class Address
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $ownerType,
        public string $ownerUid,
        public string $addressType,
        public ?string $recipientName,
        public ?string $companyName,
        public string $line1,
        public ?string $line2,
        public string $city,
        public ?string $region,
        public ?string $postalCode,
        public string $countryCode,
        public bool $isPrimary,
        public array $metadata = [],
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        string $organizationId,
        string $ownerType,
        string $ownerUid,
        string $line1,
        string $city,
        string $countryCode,
        string $addressType = 'billing',
        ?string $recipientName = null,
        ?string $companyName = null,
        ?string $line2 = null,
        ?string $region = null,
        ?string $postalCode = null,
        bool $isPrimary = false,
        array $metadata = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            ownerType: $ownerType,
            ownerUid: $ownerUid,
            addressType: $addressType,
            recipientName: $recipientName,
            companyName: $companyName,
            line1: $line1,
            line2: $line2,
            city: $city,
            region: $region,
            postalCode: $postalCode,
            countryCode: strtoupper($countryCode),
            isPrimary: $isPrimary,
            metadata: $metadata,
        );
    }
}
