<?php

declare(strict_types=1);

namespace Kontor\AI\Application;

use Kontor\AI\Infrastructure\Registry\AIProviderRegistry;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;

/**
 * The single entry point business code calls to run an AI request
 * through whichever registered provider supports it. kontor.md#32:
 * "Kontor AI is optional" — no configured provider for a capability is a
 * graceful `AIResponse::success = false`, never an exception; every
 * caller (`SummaryService`/`DraftingService`/`ExtractionService`) must
 * already handle AI simply not being available.
 */
final class AIGateway
{
    public function __construct(
        private readonly AIProviderRegistry $providers,
    ) {
    }

    public function execute(AIRequest $request): AIResponse
    {
        $provider = $this->providers->find($request->capability);

        if ($provider === null) {
            return new AIResponse(
                success: false,
                errorMessage: "No AI provider supports capability \"{$request->capability}\".",
            );
        }

        return $provider->execute($request);
    }
}
