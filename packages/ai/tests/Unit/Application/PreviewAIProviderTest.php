<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Unit\Application;

use Kontor\AI\Infrastructure\Providers\PreviewAIProvider;
use Kontor\SDK\DTO\AIRequest;
use PHPUnit\Framework\TestCase;

final class PreviewAIProviderTest extends TestCase
{
    public function test_summary_is_deterministic_and_local(): void
    {
        $response = (new PreviewAIProvider())->execute(new AIRequest(
            'summarize',
            'org_1',
            ['text' => 'A compact test summary.'],
            requiresConfirmation: false,
        ));

        $this->assertTrue($response->success);
        $this->assertSame('A compact test summary.', $response->output['summary']);
        $this->assertSame('local-preview', $response->output['mode']);
        $this->assertFalse($response->requiresConfirmation);
    }

    public function test_draft_preserves_the_confirmation_gate(): void
    {
        $response = (new PreviewAIProvider())->execute(new AIRequest(
            'draft',
            'org_1',
            ['instructions' => 'Write a reply.', 'context' => ['subject' => 'Invoice']],
            requiresConfirmation: true,
        ));

        $this->assertTrue($response->success);
        $this->assertTrue($response->requiresConfirmation);
        $this->assertStringContainsString('review required', $response->output['draft']);
    }

    public function test_extraction_returns_every_requested_field(): void
    {
        $response = (new PreviewAIProvider())->execute(new AIRequest(
            'extract',
            'org_1',
            ['text' => 'Invoice 123', 'schema' => ['invoiceNumber' => 'string', 'amount' => 'decimal']],
            requiresConfirmation: false,
        ));

        $this->assertSame(['invoiceNumber', 'amount'], array_keys($response->output['fields']));
    }
}
