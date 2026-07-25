<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Registry;

use Kontor\SDK\Contracts\ReportProviderInterface;
use RuntimeException;

/**
 * Components register a ReportProviderInterface per report
 * (kontor.md#9.9). Mirrors ImportProviderRegistry/ExportProviderRegistry —
 * ReportProviderInterface has existed in the SDK since Substage 0.2 but
 * had no registry until kontor/crm's PipelineReportProvider became its
 * first real consumer.
 */
final class ReportProviderRegistry
{
    /**
     * @var array<string, ReportProviderInterface>
     */
    private array $providers = [];

    public function register(ReportProviderInterface $provider): void
    {
        $this->providers[$provider->key()] = $provider;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function get(string $key): ReportProviderInterface
    {
        return $this->providers[$key]
            ?? throw new RuntimeException("No report provider is registered for key \"{$key}\".");
    }

    /**
     * @return array<string, ReportProviderInterface>
     */
    public function all(): array
    {
        return $this->providers;
    }
}
