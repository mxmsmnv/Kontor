<?php

declare(strict_types=1);

namespace Kontor\MCP\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class KontorMcpModuleContractTest extends TestCase
{
    public function test_documented_tools_have_unique_declarations_and_public_handlers(): void
    {
        $packageRoot = dirname(__DIR__, 2);
        $module = (string) file_get_contents($packageRoot . '/KontorMCP.module.php');
        $api = (string) file_get_contents($packageRoot . '/API.md');

        preg_match_all('/\$this->tool\(\s*\'([^\']+)\'/', $module, $toolMatches);
        preg_match_all('/\[\$this, \'([^\']+)\'\]/', $module, $handlerMatches);

        $expectedTools = [
            'kontor_status',
            'kontor_components',
            'kontor_resources',
            'kontor_search',
            'kontor_records_list',
            'kontor_record_get',
            'kontor_record_validate',
            'kontor_record_create',
            'kontor_record_update',
            'kontor_record_archive',
            'kontor_settings_export',
            'kontor_settings_preview',
            'kontor_settings_apply',
        ];

        $this->assertSame($expectedTools, $toolMatches[1]);
        $this->assertCount(13, array_unique($toolMatches[1]));
        $this->assertCount(13, $handlerMatches[1]);
        $this->assertStringContainsString('publishes 13 tools', $api);

        foreach ($handlerMatches[1] as $handler) {
            $this->assertMatchesRegularExpression(
                '/public function ' . preg_quote($handler, '/') . '\\s*\\(/',
                $module,
                "MCP handler {$handler} is not public.",
            );
        }
    }
    public function test_task_tools_are_optional_documented_and_expose_closed_schemas(): void
    {
        $provider = new class {
            use \Kontor\MCP\Application\TaskTools;

            public bool $installed = false;
            public object $modules;

            public function __construct()
            {
                $this->modules = $this;
            }

            public function wire(): self
            {
                return $this;
            }

            public function isInstalled(string $module): bool
            {
                return $this->installed && $module === 'KontorTasks';
            }

            public function uidSchema(): array
            {
                return ['type' => 'string'];
            }

            public function idempotencySchema(): array
            {
                return ['type' => 'string'];
            }

            public function tool(string $name, string $title, string $description, callable $handler, string $scope, array $properties, array $required = [], bool $destructive = false): array
            {
                return compact('name', 'handler', 'scope', 'properties', 'required', 'destructive');
            }

            public function tools(): array
            {
                return $this->taskTools();
            }
        };
        self::assertSame([], $provider->tools());
        $provider->installed = true;
        $tools = array_column($provider->tools(), null, 'name');
        $docs = file_get_contents(dirname(__DIR__, 2) . '/API.md');

        self::assertCount(7, $tools);
        foreach ($tools as $tool) {
            self::assertIsCallable($tool['handler']);
            self::assertStringContainsString($tool['name'], $docs);
        }
        self::assertFalse($tools['kontor_task_create']['properties']['fields']['additionalProperties']);
        self::assertFalse($tools['kontor_task_create']['properties']['related_customer']['additionalProperties']);
        self::assertTrue($tools['kontor_task_reviewed_action']['destructive']);
        self::assertSame('APPLY_REVIEWED_KONTOR_TASK_ACTION', $tools['kontor_task_reviewed_action']['properties']['confirmation']['const']);
    }

}
