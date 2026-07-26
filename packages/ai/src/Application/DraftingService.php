<?php

declare(strict_types=1);

namespace Kontor\AI\Application;

use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;

/**
 * The "drafting" milestone. A draft mimics ready-to-send content (an
 * email reply, a document paragraph, …), so it defaults to requiring
 * confirmation (kontor.md#32) even though drafting itself changes
 * nothing — the risk is a caller surfacing it as already-reviewed
 * without a human actually looking at it first.
 */
final class DraftingService
{
    private const CAPABILITY = 'draft';

    public function __construct(
        private readonly AIGateway $gateway,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function draft(string $organizationId, array $context, string $instructions, ?string $actorId = null): AIResponse
    {
        return $this->gateway->execute(new AIRequest(
            capability: self::CAPABILITY,
            organizationId: $organizationId,
            input: ['context' => $context, 'instructions' => $instructions],
            requiresConfirmation: true,
            actorId: $actorId,
        ));
    }
}
