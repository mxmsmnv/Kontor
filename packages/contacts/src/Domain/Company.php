<?php

declare(strict_types=1);

namespace Kontor\Contacts\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#12.2
 */
final class Company
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $legalName,
        public ?string $tradingName,
        public ?string $registrationNumber,
        public ?string $taxNumber,
        public ?string $vatNumber,
        public ?string $website,
        public ?string $email,
        public ?string $phone,
        public string $preferredLanguage,
        public ?string $preferredCurrency,
        public ?int $paymentTermsDays,
        public ?int $creditLimitMinor,
        public ?string $creditLimitCurrency,
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
        string $legalName,
        ?string $tradingName = null,
        ?string $registrationNumber = null,
        ?string $taxNumber = null,
        ?string $vatNumber = null,
        ?string $website = null,
        ?string $email = null,
        ?string $phone = null,
        string $preferredLanguage = 'en',
        ?string $preferredCurrency = null,
        ?int $paymentTermsDays = null,
        ?int $creditLimitMinor = null,
        ?string $creditLimitCurrency = null,
        string $status = 'active',
        ?int $assignedUserId = null,
        ?string $notes = null,
        array $metadata = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            legalName: $legalName,
            tradingName: $tradingName,
            registrationNumber: $registrationNumber,
            taxNumber: $taxNumber,
            vatNumber: $vatNumber,
            website: $website,
            email: $email,
            phone: $phone,
            preferredLanguage: $preferredLanguage,
            preferredCurrency: $preferredCurrency,
            paymentTermsDays: $paymentTermsDays,
            creditLimitMinor: $creditLimitMinor,
            creditLimitCurrency: $creditLimitCurrency,
            status: $status,
            assignedUserId: $assignedUserId,
            notes: $notes,
            metadata: $metadata,
        );
    }
}
