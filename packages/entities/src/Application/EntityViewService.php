<?php

declare(strict_types=1);

namespace Kontor\Entities\Application;

use Kontor\Entities\Domain\EntityRecord;
use Kontor\Entities\Domain\EntityView;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityViewRepository;

/**
 * The "views" milestone. apply() is real filter/sort logic over a
 * definition's records, not just storage of the view's own configuration
 * — the same small operator vocabulary kontor/automation's
 * ConditionEvaluator uses (equals/not_equals/greater_than/less_than/
 * contains), duplicated here rather than a cross-package dependency for
 * a few lines of comparison logic (the same call made for kontor/tasks'
 * and kontor/reports' own recurrence-interval tables).
 */
final class EntityViewService
{
    public function __construct(
        private readonly EntityViewRepository $views,
        private readonly EntityRecordRepository $records,
    ) {
    }

    /**
     * @param array<int, array{field: string, operator: string, value: mixed}> $filters
     * @param array<int, array{field: string, direction: string}> $sort
     * @param string[] $columns
     */
    public function createView(string $organizationUid, string $definitionUid, string $name, array $filters = [], array $sort = [], array $columns = [], ?int $createdBy = null): EntityView
    {
        $view = EntityView::create($organizationUid, $definitionUid, $name, $filters, $sort, $columns, $createdBy);
        $this->views->save($view);

        return $view;
    }

    /**
     * Fetches the view's definition's records and applies its filters/
     * sort — the DB-touching convenience wrapper around the pure logic
     * below.
     *
     * @return EntityRecord[]
     */
    public function apply(EntityView $view): array
    {
        return $this->sortRecords(
            $this->filterRecords($this->records->forDefinition($view->definitionUid), $view->filters),
            $view->sort,
        );
    }

    /**
     * @param EntityRecord[] $records
     * @param array<int, array{field: string, operator: string, value: mixed}> $filters
     * @return EntityRecord[]
     */
    public function filterRecords(array $records, array $filters): array
    {
        return array_values(array_filter(
            $records,
            fn (EntityRecord $record): bool => $this->matchesAllFilters($record, $filters),
        ));
    }

    /**
     * @param EntityRecord[] $records
     * @param array<int, array{field: string, direction: string}> $sort
     * @return EntityRecord[]
     */
    public function sortRecords(array $records, array $sort): array
    {
        foreach (array_reverse($sort) as $criterion) {
            $field = $criterion['field'];
            $direction = ($criterion['direction'] ?? 'asc') === 'desc' ? -1 : 1;

            usort($records, static function (EntityRecord $a, EntityRecord $b) use ($field, $direction): int {
                return $direction * self::compare($a->data[$field] ?? null, $b->data[$field] ?? null);
            });
        }

        return $records;
    }

    /**
     * @param array<int, array{field: string, operator: string, value: mixed}> $filters
     */
    private function matchesAllFilters(EntityRecord $record, array $filters): bool
    {
        foreach ($filters as $filter) {
            if (!$this->matchesFilter($record, $filter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array{field: string, operator: string, value: mixed} $filter
     */
    private function matchesFilter(EntityRecord $record, array $filter): bool
    {
        $actual = $record->data[$filter['field']] ?? null;
        $expected = $filter['value'] ?? null;

        return match ($filter['operator']) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'greater_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            default => false,
        };
    }

    private static function compare(mixed $a, mixed $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a <=> (float) $b;
        }

        return (string) $a <=> (string) $b;
    }
}
