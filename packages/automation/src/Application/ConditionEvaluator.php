<?php

declare(strict_types=1);

namespace Kontor\Automation\Application;

use Kontor\Automation\Domain\RuleCondition;

/**
 * The "conditions" milestone's actual evaluation — pure logic, no
 * persistence. AND semantics: every condition must pass. `field` is a
 * dot-path into the triggering event's data array, resolved the same way
 * kontor/documents' TemplateEngine resolves placeholder paths.
 */
final class ConditionEvaluator
{
    /**
     * @param RuleCondition[] $conditions
     * @param array<string, mixed> $eventData
     */
    public function evaluate(array $conditions, array $eventData): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateOne($condition, $eventData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $eventData
     */
    private function evaluateOne(RuleCondition $condition, array $eventData): bool
    {
        $actual = $this->resolve($condition->field, $eventData);
        $expected = $condition->value;

        return match ($condition->operator) {
            'equals' => $this->stringify($actual) === $expected,
            'not_equals' => $this->stringify($actual) !== $expected,
            'greater_than' => is_numeric($actual) && $expected !== null && (float) $actual > (float) $expected,
            'less_than' => is_numeric($actual) && $expected !== null && (float) $actual < (float) $expected,
            'contains' => is_string($actual) && $expected !== null && str_contains($actual, $expected),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolve(string $path, array $data): mixed
    {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
