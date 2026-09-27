<?php

declare(strict_types=1);

namespace Kontor\MCP\Health;

use Kontor\MCP\Application\KontorMcpBridge;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class McpIntegrationHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly KontorMcpBridge $bridge)
    {
    }

    public function key(): string
    {
        return 'mcp';
    }

    public function run(): HealthCheckResult
    {
        $status = $this->bridge->status();
        $resources = (int) ($status['resources'] ?? 0);

        return $resources > 0
            ? new HealthCheckResult('ok', "MCP integration is ready with {$resources} resource(s).")
            : new HealthCheckResult('warning', 'MCP integration is installed, but no Kontor API resources are registered.');
    }
}
