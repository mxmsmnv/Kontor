<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Unit\Application;

use Kontor\AI\Application\AIGateway;
use Kontor\AI\Application\DraftingService;
use Kontor\AI\Application\ExtractionService;
use Kontor\AI\Application\SummaryService;
use Kontor\AI\Infrastructure\Registry\AIProviderRegistry;
use Kontor\SDK\Contracts\KontorAIProviderInterface;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;
use PHPUnit\Framework\TestCase;

final class CapabilityServicesTest extends TestCase
{
    /**
     * A provider that echoes back exactly the AIRequest it received, so
     * tests can assert on capability/input/requiresConfirmation without
     * needing Squad at all.
     */
    private function echoingProvider(): KontorAIProviderInterface
    {
        return new class implements KontorAIProviderInterface {
            public ?AIRequest $lastRequest = null;

            public function supports(string $capability): bool
            {
                return true;
            }

            public function execute(AIRequest $request): AIResponse
            {
                $this->lastRequest = $request;

                return new AIResponse(success: true, output: ['echo' => $request->input], requiresConfirmation: $request->requiresConfirmation);
            }
        };
    }

    public function test_summary_service_never_requires_confirmation(): void
    {
        $provider = $this->echoingProvider();
        $registry = new AIProviderRegistry();
        $registry->register($provider);

        $response = (new SummaryService(new AIGateway($registry)))->summarize('org_1', 'Some text.');

        $this->assertSame('summarize', $provider->lastRequest->capability);
        $this->assertSame(['text' => 'Some text.'], $provider->lastRequest->input);
        $this->assertFalse($response->requiresConfirmation);
    }

    public function test_drafting_service_always_requires_confirmation(): void
    {
        $provider = $this->echoingProvider();
        $registry = new AIProviderRegistry();
        $registry->register($provider);

        $response = (new DraftingService(new AIGateway($registry)))->draft('org_1', ['subject' => 'Re: invoice'], 'Be polite.');

        $this->assertSame('draft', $provider->lastRequest->capability);
        $this->assertSame(['context' => ['subject' => 'Re: invoice'], 'instructions' => 'Be polite.'], $provider->lastRequest->input);
        $this->assertTrue($response->requiresConfirmation);
    }

    public function test_extraction_service_never_requires_confirmation(): void
    {
        $provider = $this->echoingProvider();
        $registry = new AIProviderRegistry();
        $registry->register($provider);

        $response = (new ExtractionService(new AIGateway($registry)))->extract('org_1', 'Invoice #123 due $500', ['invoiceNumber' => 'string', 'amount' => 'decimal']);

        $this->assertSame('extract', $provider->lastRequest->capability);
        $this->assertFalse($response->requiresConfirmation);
    }
}
