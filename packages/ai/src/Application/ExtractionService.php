<?php

declare(strict_types=1);

namespace Kontor\AI\Application;

use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;

/**
 * The "extraction" milestone: propose structured fields out of
 * unstructured text. Never requires confirmation on its own — it only
 * proposes data, nothing is created or changed until a caller explicitly
 * acts on the result.
 */
final class ExtractionService
{
    private const CAPABILITY = 'extract';

    public function __construct(
        private readonly AIGateway $gateway,
    ) {
    }

    /**
     * @param array<string, string> $schema field name => scalar type
     */
    public function extract(string $organizationId, string $text, array $schema, ?string $actorId = null): AIResponse
    {
        return $this->gateway->execute(new AIRequest(
            capability: self::CAPABILITY,
            organizationId: $organizationId,
            input: ['text' => $text, 'schema' => $schema],
            requiresConfirmation: false,
            actorId: $actorId,
        ));
    }
}
