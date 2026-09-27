<?php

declare(strict_types=1);

namespace Kontor\Entities\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class EntityField
{
    public const TYPES = ['string', 'int', 'decimal', 'bool', 'date', 'datetime'];

    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $definitionUid,
        public readonly string $fieldKey,
        public string $label,
        public readonly string $fieldType,
        public bool $required,
        public int $sortOrder,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(
        string $organizationId,
        string $definitionUid,
        string $fieldKey,
        string $label,
        string $fieldType,
        bool $required = false,
        int $sortOrder = 0,
    ): self {
        if (!in_array($fieldType, self::TYPES, true)) {
            throw new \InvalidArgumentException("\"{$fieldType}\" is not a supported field type.");
        }

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            definitionUid: $definitionUid,
            fieldKey: $fieldKey,
            label: $label,
            fieldType: $fieldType,
            required: $required,
            sortOrder: $sortOrder,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
