<?php

declare(strict_types=1);

namespace Kontor\AI\DTO;

use Kontor\AI\Domain\PendingAIAction;
use Kontor\SDK\DTO\AIResponse;

/**
 * Either the AI response completed immediately, or it's now a
 * `PendingAIAction` awaiting a human decision — see
 * `AIActionApprovalService::requestAndMaybeApprove()`.
 */
final class AIActionResult
{
    private function __construct(
        public readonly bool $isPending,
        public readonly ?AIResponse $response,
        public readonly ?PendingAIAction $pendingAction,
    ) {
    }

    public static function completed(AIResponse $response): self
    {
        return new self(false, $response, null);
    }

    public static function pending(PendingAIAction $pendingAction): self
    {
        return new self(true, null, $pendingAction);
    }
}
