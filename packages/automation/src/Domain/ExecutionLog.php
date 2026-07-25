<?php

declare(strict_types=1);

namespace Kontor\Automation\Domain;

use Kontor\SDK\ValueObjects\Uid;

final class ExecutionLog
{
    /**
     * @param array<int, array{actionKey: string, result: mixed}> $actionsResult
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly ?string $ruleUid,
        public readonly string $triggerEvent,
        public readonly bool $matched,
        public readonly bool $dryRun,
        public readonly bool $recursionBlocked,
        public readonly array $actionsResult,
        public readonly ?string $error,
        public readonly \DateTimeImmutable $occurredAt,
    ) {
    }

    /**
     * @param array<int, array{actionKey: string, result: mixed}> $actionsResult
     */
    public static function create(
        string $organizationId,
        ?string $ruleUid,
        string $triggerEvent,
        bool $matched,
        bool $dryRun = false,
        bool $recursionBlocked = false,
        array $actionsResult = [],
        ?string $error = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            ruleUid: $ruleUid,
            triggerEvent: $triggerEvent,
            matched: $matched,
            dryRun: $dryRun,
            recursionBlocked: $recursionBlocked,
            actionsResult: $actionsResult,
            error: $error,
            occurredAt: new \DateTimeImmutable(),
        );
    }
}
