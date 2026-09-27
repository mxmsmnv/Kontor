<?php

declare(strict_types=1);

namespace Kontor\Automation\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class RuleAction
{
    /**
     * @param array<string, mixed> $params
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $ruleUid,
        public readonly string $actionKey,
        public readonly array $params,
        public readonly int $sortOrder,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function create(string $organizationId, string $ruleUid, string $actionKey, array $params = [], int $sortOrder = 0): self
    {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            ruleUid: $ruleUid,
            actionKey: $actionKey,
            params: $params,
            sortOrder: $sortOrder,
            createdAt: new \DateTimeImmutable(),
        );
    }
}
