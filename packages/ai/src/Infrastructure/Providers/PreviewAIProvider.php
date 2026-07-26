<?php

declare(strict_types=1);

namespace Kontor\AI\Infrastructure\Providers;

use Kontor\SDK\Contracts\KontorAIProviderInterface;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;

/**
 * Deterministic, local-only provider for the admin workbench. It is never
 * registered in the production provider registry and never makes a network
 * request, so administrators can verify capability and approval wiring safely.
 */
final class PreviewAIProvider implements KontorAIProviderInterface
{
    private const CAPABILITIES = ['summarize', 'draft', 'extract'];

    public function supports(string $capability): bool
    {
        return in_array($capability, self::CAPABILITIES, true);
    }

    public function execute(AIRequest $request): AIResponse
    {
        if (!$this->supports($request->capability)) {
            return new AIResponse(success: false, errorMessage: 'Preview provider does not support this capability.');
        }

        $output = match ($request->capability) {
            'summarize' => $this->summarize((string) ($request->input['text'] ?? '')),
            'draft' => $this->draft($request->input),
            'extract' => $this->extract(
                (string) ($request->input['text'] ?? ''),
                is_array($request->input['schema'] ?? null) ? $request->input['schema'] : [],
            ),
        };

        return new AIResponse(
            success: true,
            output: $output,
            requiresConfirmation: $request->requiresConfirmation,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(string $text): array
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $text));

        return [
            'summary' => mb_strlen($normalized) > 180
                ? mb_substr($normalized, 0, 177) . '...'
                : $normalized,
            'characters' => mb_strlen($normalized),
            'mode' => 'local-preview',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function draft(array $input): array
    {
        return [
            'draft' => trim((string) ($input['instructions'] ?? ''))
                . "\n\n[Local preview draft — review required]",
            'context' => is_array($input['context'] ?? null) ? $input['context'] : [],
            'mode' => 'local-preview',
        ];
    }

    /**
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private function extract(string $text, array $schema): array
    {
        $fields = [];
        foreach ($schema as $field => $type) {
            $fields[(string) $field] = [
                'value' => '[preview] ' . (string) $field,
                'type' => (string) $type,
            ];
        }

        return [
            'fields' => $fields,
            'sourceCharacters' => mb_strlen($text),
            'mode' => 'local-preview',
        ];
    }
}
