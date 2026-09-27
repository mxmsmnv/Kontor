<?php

declare(strict_types=1);

namespace Kontor\Automation\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class RuleCondition
{
    public const OPERATORS = ['equals', 'not_equals', 'greater_than', 'less_than', 'contains'];

    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $ruleUid,
        public readonly string $field,
        public readonly string $operator,
        public readonly ?string $value,
        public readonly int $sortOrder,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public static function create(string $organizationId, string $ruleUid, string $field, string $operator, ?string $value, int $sortOrder = 0): self
    {
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("\"{$operator}\" is not a supported condition operator.");
        }

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            ruleUid: $ruleUid,
            field: $field,
            operator: $operator,
            value: $value,
            sortOrder: $sortOrder,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
