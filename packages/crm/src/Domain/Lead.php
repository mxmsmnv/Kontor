<?php

declare(strict_types=1);

namespace Kontor\CRM\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#13.1
 */
final class Lead
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $title,
        public ?string $contactUid,
        public ?string $companyUid,
        public ?string $source,
        public string $status,
        public string $priority,
        public ?Money $estimatedValue,
        public ?int $assignedUserId,
        public ?\DateTimeImmutable $nextActionAt,
        public ?string $convertedDealUid,
        public ?string $lostReason,
        public ?string $description,
    ) {
    }

    public static function create(
        string $organizationId,
        string $title,
        ?string $contactUid = null,
        ?string $companyUid = null,
        ?string $source = null,
        string $priority = 'medium',
        ?Money $estimatedValue = null,
        ?int $assignedUserId = null,
        ?\DateTimeImmutable $nextActionAt = null,
        ?string $description = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            title: $title,
            contactUid: $contactUid,
            companyUid: $companyUid,
            source: $source,
            status: 'new',
            priority: $priority,
            estimatedValue: $estimatedValue,
            assignedUserId: $assignedUserId,
            nextActionAt: $nextActionAt,
            convertedDealUid: null,
            lostReason: null,
            description: $description,
        );
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    /**
     * kontor.md diagram 17.1 "Qualified?" — a lead must already be linked
     * to a contact or company before it can become a deal; the schema has
     * no name/email columns of its own to create one from.
     */
    public function isQualifiedForConversion(): bool
    {
        return $this->contactUid !== null || $this->companyUid !== null;
    }
}
