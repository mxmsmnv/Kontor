<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Unit\Application;

use Kontor\AI\Contracts\SquadClientInterface;
use Kontor\AI\Infrastructure\Providers\NullSquadClient;
use Kontor\AI\Infrastructure\Providers\SquadAdapter;
use Kontor\SDK\DTO\AIRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SquadAdapterTest extends TestCase
{
    public function test_supports_only_the_configured_capabilities(): void
    {
        $adapter = new SquadAdapter(new NullSquadClient(), ['summarize']);

        $this->assertTrue($adapter->supports('summarize'));
        $this->assertFalse($adapter->supports('draft'));
    }

    public function test_defaults_to_the_three_named_capabilities(): void
    {
        $adapter = new SquadAdapter(new NullSquadClient());

        $this->assertTrue($adapter->supports('summarize'));
        $this->assertTrue($adapter->supports('draft'));
        $this->assertTrue($adapter->supports('extract'));
        $this->assertFalse($adapter->supports('something-else'));
    }

    public function test_execute_delegates_to_the_client_and_wraps_the_output(): void
    {
        $client = new class implements SquadClientInterface {
            public function complete(string $capability, array $input): array
            {
                return ['output' => ['summary' => 'A summary'], 'requiresConfirmation' => false];
            }
        };

        $response = (new SquadAdapter($client))->execute(new AIRequest('summarize', 'org_1', ['text' => 'hi'], requiresConfirmation: false));

        $this->assertTrue($response->success);
        $this->assertSame(['summary' => 'A summary'], $response->output);
        $this->assertFalse($response->requiresConfirmation);
    }

    public function test_execute_requires_confirmation_when_either_the_request_or_the_client_says_so(): void
    {
        $client = new class implements SquadClientInterface {
            public function complete(string $capability, array $input): array
            {
                return ['output' => [], 'requiresConfirmation' => true];
            }
        };

        $response = (new SquadAdapter($client))->execute(new AIRequest('draft', 'org_1', [], requiresConfirmation: false));

        $this->assertTrue($response->requiresConfirmation);
    }

    public function test_execute_returns_a_graceful_failure_when_the_client_throws(): void
    {
        $client = new class implements SquadClientInterface {
            public function complete(string $capability, array $input): array
            {
                throw new RuntimeException('Squad is unreachable.');
            }
        };

        $response = (new SquadAdapter($client))->execute(new AIRequest('summarize', 'org_1', []));

        $this->assertFalse($response->success);
        $this->assertSame('AI provider request failed.', $response->errorMessage);
    }

    public function test_timeout_is_redacted_and_the_same_adapter_can_retry_successfully(): void
    {
        $secret = 'squad-token-super-secret';
        $body = 'Confidential acquisition details';

        $client = new class($secret) implements SquadClientInterface {
            public int $calls = 0;

            public function __construct(private readonly string $secret)
            {
            }

            public function complete(string $capability, array $input): array
            {
                $this->calls++;

                if ($this->calls === 1) {
                    throw new RuntimeException(
                        "Timeout while sending {$input['text']} with bearer {$this->secret}",
                    );
                }

                return [
                    'output' => ['summary' => 'Safe retry result'],
                    'requiresConfirmation' => false,
                ];
            }
        };

        $adapter = new SquadAdapter($client);
        $request = new AIRequest('summarize', 'org_1', ['text' => $body], requiresConfirmation: false);

        $failed = $adapter->execute($request);

        $this->assertFalse($failed->success);
        $this->assertSame('AI provider request failed.', $failed->errorMessage);
        $this->assertStringNotContainsString($secret, (string) $failed->errorMessage);
        $this->assertStringNotContainsString($body, (string) $failed->errorMessage);

        $retried = $adapter->execute($request);

        $this->assertTrue($retried->success);
        $this->assertSame(['summary' => 'Safe retry result'], $retried->output);
        $this->assertNull($retried->errorMessage);
        $this->assertSame(2, $client->calls);
    }

    public function test_null_squad_client_throws_a_clear_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No Squad connection is configured');

        (new NullSquadClient())->complete('summarize', []);
    }
}
