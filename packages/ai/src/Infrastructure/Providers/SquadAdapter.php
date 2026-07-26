<?php

declare(strict_types=1);

namespace Kontor\AI\Infrastructure\Providers;

use Kontor\AI\Contracts\SquadClientInterface;
use Kontor\SDK\Contracts\KontorAIProviderInterface;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;

/**
 * The "Squad adapter" milestone (kontor.md#32's
 * `KontorAI → business AI contracts → Squad adapter → provider adapters`):
 * the one bridge between Kontor's own `KontorAIProviderInterface`
 * (kontor.md#9.12) and Squad, going only through `SquadClientInterface` —
 * never Squad's implementation details directly.
 */
final class SquadAdapter implements KontorAIProviderInterface
{
    /**
     * @param string[] $supportedCapabilities
     */
    public function __construct(
        private readonly SquadClientInterface $client,
        private readonly array $supportedCapabilities = ['summarize', 'draft', 'extract'],
    ) {
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->supportedCapabilities, true);
    }

    public function execute(AIRequest $request): AIResponse
    {
        try {
            $result = $this->client->complete($request->capability, $request->input);
        } catch (\Throwable $e) {
            return new AIResponse(success: false, errorMessage: $e->getMessage());
        }

        return new AIResponse(
            success: true,
            output: $result['output'] ?? [],
            requiresConfirmation: $request->requiresConfirmation || ($result['requiresConfirmation'] ?? false),
        );
    }
}
