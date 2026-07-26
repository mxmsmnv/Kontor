<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Unit\Application;

use Kontor\AI\Infrastructure\Registry\AIProviderRegistry;
use Kontor\SDK\Contracts\KontorAIProviderInterface;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;
use PHPUnit\Framework\TestCase;

final class AIProviderRegistryTest extends TestCase
{
    private function provider(bool $supports): KontorAIProviderInterface
    {
        return new class($supports) implements KontorAIProviderInterface {
            public function __construct(private readonly bool $supports)
            {
            }

            public function supports(string $capability): bool
            {
                return $this->supports;
            }

            public function execute(AIRequest $request): AIResponse
            {
                return new AIResponse(success: true);
            }
        };
    }

    public function test_find_returns_null_when_nothing_is_registered(): void
    {
        $this->assertNull((new AIProviderRegistry())->find('summarize'));
    }

    public function test_find_returns_the_first_matching_provider(): void
    {
        $registry = new AIProviderRegistry();
        $first = $this->provider(true);
        $second = $this->provider(true);
        $registry->register($first);
        $registry->register($second);

        $this->assertSame($first, $registry->find('summarize'));
    }

    public function test_all_returns_every_registered_provider(): void
    {
        $registry = new AIProviderRegistry();
        $registry->register($this->provider(true));
        $registry->register($this->provider(false));

        $this->assertCount(2, $registry->all());
    }
}
