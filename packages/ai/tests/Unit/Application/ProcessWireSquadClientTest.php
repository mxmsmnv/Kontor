<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Unit\Application;

use Kontor\AI\Infrastructure\Providers\ProcessWireSquadClient;
use PHPUnit\Framework\TestCase;

final class ProcessWireSquadClientTest extends TestCase
{
    public function test_deepseek_extraction_keeps_only_requested_fields(): void
    {
        $client = new ProcessWireSquadClient(static function (string $message, array $options): array {
            self::assertSame('deepseek', $options['provider']);
            self::assertFalse($options['cache']);
            self::assertFalse($options['webSearch']);
            self::assertSame('Synthetic note', json_decode($message, true)['text']);

            return ['success' => true, 'content' => '{"fields":{"name":"Demo","unexpected":"ignored"}}'];
        }, 'deepseek');

        self::assertSame(['output' => ['fields' => ['name' => 'Demo']]], $client->complete('extract', [
            'text' => 'Synthetic note',
            'schema' => ['name' => 'string'],
        ]));
    }

    public function test_extraction_accepts_a_flat_object_with_requested_fields(): void
    {
        $client = new ProcessWireSquadClient(static fn (): array => [
            'success' => true,
            'content' => '{"Person":"display_name","unrequested":"ignored"}',
        ], 'deepseek');
        self::assertSame(['output' => ['fields' => ['Person' => 'display_name']]], $client->complete('extract', [
            'text' => 'Map Person',
            'schema' => ['Person' => 'one of: display_name, or null'],
        ]));
    }

    public function test_failed_provider_does_not_return_provider_details(): void
    {
        $client = new ProcessWireSquadClient(static fn (): array => ['success' => false, 'error' => 'provider details'], 'deepseek');
        $this->expectExceptionMessage('Squad request failed.');
        $client->complete('summarize', ['text' => 'Demo']);
    }
}
