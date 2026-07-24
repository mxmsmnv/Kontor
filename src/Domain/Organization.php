<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#11.1. The unit of tenant isolation (kontor.md#4.2) — every
 * business table carries an organization_id, and single-company installs
 * use exactly one default Organization.
 */
final class Organization
{
    public function __construct(
        public readonly Uid $uid,
        public string $name,
        public ?string $legalName,
        public string $countryCode,
        public string $defaultLanguage,
        public string $defaultCurrency,
        public string $timezone,
        public string $status,
        public array $settings = [],
    ) {
    }

    public static function createDefault(string $countryCode, string $defaultLanguage, string $defaultCurrency): self
    {
        return new self(
            uid: Uid::generate(),
            name: 'Default organization',
            legalName: null,
            countryCode: $countryCode,
            defaultLanguage: $defaultLanguage,
            defaultCurrency: $defaultCurrency,
            timezone: 'UTC',
            status: 'active',
        );
    }
}
