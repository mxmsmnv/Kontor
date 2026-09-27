<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Unit\Application;

use Kontor\AI\Application\AIGateway;
use Kontor\AI\Infrastructure\Registry\AIProviderRegistry;
use Kontor\SDK\Contracts\KontorAIProviderInterface;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;
use PHPUnit\Framework\TestCase;

final class AIGatewayTest extends TestCase
{
    public function test_no_provider_configured_is_a_graceful_failure(): void
    {
        $gateway = new AIGateway(new AIProviderRegistry());

        $response = $gateway->execute(new AIRequest('summarize', 'org_1', ['text' => 'hi']));

        $this->assertFalse($response->success);
        $this->assertStringContainsString('summarize', $response->errorMessage);
    }

    public function test_the_first_supporting_provider_handles_the_request(): void
    {
        $provider = new class implements KontorAIProviderInterface {
            public function supports(string $capability): bool
            {
                return $capability === 'summarize';
            }

            public function execute(AIRequest $request): AIResponse
            {
                return new AIResponse(success: true, output: ['summary' => 'ok']);
            }
        };

        $registry = new AIProviderRegistry();
        $registry->register($provider);

        $response = (new AIGateway($registry))->execute(new AIRequest('summarize', 'org_1', ['text' => 'hi']));

        $this->assertTrue($response->success);
        $this->assertSame(['summary' => 'ok'], $response->output);
    }

    public function test_a_provider_that_does_not_support_the_capability_is_skipped(): void
    {
        $nonMatching = new class implements KontorAIProviderInterface {
            public function supports(string $capability): bool
            {
                return false;
            }

            public function execute(AIRequest $request): AIResponse
            {
                throw new \RuntimeException('should never be called');
            }
        };

        $matching = new class implements KontorAIProviderInterface {
            public function supports(string $capability): bool
            {
                return true;
            }

            public function execute(AIRequest $request): AIResponse
            {
                return new AIResponse(success: true, output: ['ok' => true]);
            }
        };

        $registry = new AIProviderRegistry();
        $registry->register($nonMatching);
        $registry->register($matching);

        $response = (new AIGateway($registry))->execute(new AIRequest('draft', 'org_1', []));

        $this->assertTrue($response->success);
    }
}
