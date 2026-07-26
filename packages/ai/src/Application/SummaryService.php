<?php

declare(strict_types=1);

namespace Kontor\AI\Application;

use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;

/**
 * The "summaries" milestone. Read-only and informational — nothing is
 * acted on directly from a summary, so it never requires confirmation.
 */
final class SummaryService
{
    private const CAPABILITY = 'summarize';

    public function __construct(
        private readonly AIGateway $gateway,
    ) {
    }

    public function summarize(string $organizationId, string $text, ?string $actorId = null): AIResponse
    {
        return $this->gateway->execute(new AIRequest(
            capability: self::CAPABILITY,
            organizationId: $organizationId,
            input: ['text' => $text],
            requiresConfirmation: false,
            actorId: $actorId,
        ));
    }
}
