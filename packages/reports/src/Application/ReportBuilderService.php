<?php

declare(strict_types=1);

namespace Kontor\Reports\Application;

use InvalidArgumentException;
use Kontor\Core\Infrastructure\Registry\ReportProviderRegistry;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\ReportResult;
use Kontor\SDK\DTO\ReportSchema;

/**
 * The "report builder" milestone. kontor/core's ReportProviderRegistry
 * already exists (Substage 3.3, first consumer kontor/crm's
 * PipelineReportProvider) — this is the orchestration layer on top of it:
 * validating that a query's filters/groupBy are actually ones the chosen
 * provider declared as filterable/groupable in its own ReportSchema()
 * before executing, rather than letting an invalid field silently produce
 * an empty or wrong result. ReportProviderInterface's fixed-shape design
 * (kontor.md#9.9) means this validates against a provider's declared
 * fields rather than building arbitrary SQL — a fully generic ad-hoc query
 * engine is out of scope, same boundary PipelineReportProvider's own doc
 * comment already drew.
 */
final class ReportBuilderService
{
    public function __construct(private readonly ReportProviderRegistry $providers)
    {
    }

    public function run(string $providerKey, ReportQuery $query): ReportResult
    {
        $provider = $this->providers->get($providerKey);
        $this->validate($provider->schema(), $providerKey, $query->filters, $query->groupBy);

        return $provider->execute($query);
    }

    /**
     * Validates filters/groupBy against a provider's schema without
     * executing it — for callers (like scheduling a report) that need to
     * fail fast on a bad field without paying for a query they're not
     * ready to run yet.
     *
     * @param array<string, mixed> $filters
     * @param string[] $groupBy
     */
    public function validateFor(string $providerKey, array $filters, array $groupBy): void
    {
        $this->validate($this->providers->get($providerKey)->schema(), $providerKey, $filters, $groupBy);
    }

    /**
     * @param array<string, mixed> $filters
     * @param string[] $groupBy
     */
    private function validate(ReportSchema $schema, string $providerKey, array $filters, array $groupBy): void
    {
        foreach (array_keys($filters) as $field) {
            if (!in_array($field, $schema->filterableFields, true)) {
                throw new InvalidArgumentException("\"{$field}\" is not a filterable field for report \"{$providerKey}\".");
            }
        }

        foreach ($groupBy as $field) {
            if (!in_array($field, $schema->groupableFields, true)) {
                throw new InvalidArgumentException("\"{$field}\" is not a groupable field for report \"{$providerKey}\".");
            }
        }
    }
}
