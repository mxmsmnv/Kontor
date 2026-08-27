<?php

declare(strict_types=1);

namespace Kontor\Settings\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;
use Kontor\Settings\Application\SettingsProviderRegistry;

final class SettingsHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly SettingsProviderRegistry $providers)
    {
    }

    public function key(): string
    {
        return 'settings';
    }

    public function run(): HealthCheckResult
    {
        $count = count($this->providers->all());

        return $count > 0
            ? new HealthCheckResult('ok', "Settings migration is ready with {$count} provider(s).")
            : new HealthCheckResult('warning', 'No settings migration providers are registered.');
    }
}
