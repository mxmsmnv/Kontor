<?php

declare(strict_types=1);

namespace Kontor\Entities\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class EntityView
{
    /**
     * @param array<int, array{field: string, operator: string, value: mixed}> $filters
     * @param array<int, array{field: string, direction: string}> $sort
     * @param string[] $columns
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $definitionUid,
        public string $name,
        public array $filters,
        public array $sort,
        public array $columns,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    /**
     * @param array<int, array{field: string, operator: string, value: mixed}> $filters
     * @param array<int, array{field: string, direction: string}> $sort
     * @param string[] $columns
     */
    public static function create(
        string $organizationId,
        string $definitionUid,
        string $name,
        array $filters = [],
        array $sort = [],
        array $columns = [],
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            definitionUid: $definitionUid,
            name: $name,
            filters: $filters,
            sort: $sort,
            columns: $columns,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }
}
