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
}
