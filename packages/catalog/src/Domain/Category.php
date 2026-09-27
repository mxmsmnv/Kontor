<?php

declare(strict_types=1);

namespace Kontor\Catalog\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class Category
{
    /**
     * @param array<string, string> $name locale => text
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public ?string $parentUid,
        public array $name,
        public int $sortOrder,
        public string $status,
    ) {
    }

    /**
     * @param array<string, string> $name
     */
    public static function create(
        string $organizationId,
        array $name,
        ?string $parentUid = null,
        int $sortOrder = 0,
        string $status = 'active',
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            parentUid: $parentUid,
            name: $name,
            sortOrder: $sortOrder,
            status: $status,
        );
    }

    public function nameIn(string $locale, string $fallback = 'en'): ?string
    {
        return $this->name[$locale] ?? $this->name[$fallback] ?? null;
    }
}
