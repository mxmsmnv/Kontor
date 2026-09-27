<?php

declare(strict_types=1);

namespace Kontor\CRM\Domain;

use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#13.4
 */
final class Deal
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $pipelineUid,
        public string $stageUid,
        public string $title,
        public ?string $contactUid,
        public ?string $companyUid,
        public ?int $assignedUserId,
        public ?Money $value,
        public ?int $probability,
        public ?\DateTimeImmutable $expectedCloseDate,
        public ?string $source,
        public string $status,
        public ?\DateTimeImmutable $wonAt,
        public ?\DateTimeImmutable $lostAt,
        public ?string $lostReason,
        public ?string $description,
    ) {
    }

    public static function create(
        string $organizationId,
        string $pipelineUid,
        string $stageUid,
        string $title,
        ?string $contactUid = null,
        ?string $companyUid = null,
        ?int $assignedUserId = null,
        ?Money $value = null,
        ?int $probability = null,
        ?\DateTimeImmutable $expectedCloseDate = null,
        ?string $source = null,
        ?string $description = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            pipelineUid: $pipelineUid,
            stageUid: $stageUid,
            title: $title,
            contactUid: $contactUid,
            companyUid: $companyUid,
            assignedUserId: $assignedUserId,
            value: $value,
            probability: $probability,
            expectedCloseDate: $expectedCloseDate,
            source: $source,
            status: 'open',
            wonAt: null,
            lostAt: null,
            lostReason: null,
            description: $description,
        );
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
