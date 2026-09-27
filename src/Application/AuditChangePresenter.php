<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Domain\AuditEvent;

final class AuditChangePresenter
{
    /**
     * @return array<int, array{field: string, previous: string, current: string}>
     */
    public function changes(AuditEvent $event): array
    {
        $previous = $event->previous ?? [];
        $current = $event->current ?? [];
        $fields = array_values(array_unique([
            ...array_keys($previous),
            ...array_keys($current),
        ]));
        $missing = new \stdClass();
        $changes = [];

        foreach ($fields as $field) {
            $before = array_key_exists($field, $previous) ? $previous[$field] : $missing;
            $after = array_key_exists($field, $current) ? $current[$field] : $missing;

            if (
                array_key_exists($field, $previous)
                && array_key_exists($field, $current)
                && $before === $after
            ) {
                continue;
            }

            $changes[] = [
                'field' => $this->label((string) $field),
                'previous' => $this->display($before, $missing),
                'current' => $this->display($after, $missing),
            ];
        }

        return $changes;
    }

    private function label(string $field): string
    {
        $spaced = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $field) ?? $field;

        return ucwords(str_replace(['_', '-'], ' ', $spaced));
    }

    private function display(mixed $value, \stdClass $missing): string
    {
        if ($value === $missing) {
            return 'Not set';
        }

        if ($value === null) {
            return 'Null';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return (string) json_encode(
                $value,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        }

        return (string) $value;
    }
}
