<?php

declare(strict_types=1);

namespace Kontor\Entities\Application;

use Kontor\Entities\Domain\EntityField;
use Kontor\Entities\Domain\EntityRecord;
use Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityFieldRepository;
use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;
use InvalidArgumentException;

/**
 * The "fields" milestone's other half: records are validated against
 * their definition's declared fields, not just stored blind —
 * required fields must be present, every value must match its field's
 * declared type, and a key that isn't a declared field at all is
 * rejected (catches typos rather than silently keeping dead data).
 */
final class EntityRecordService
{
    public function __construct(
        private readonly EntityDefinitionRepository $definitions,
        private readonly EntityFieldRepository $fields,
        private readonly EntityRecordRepository $records,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(string $definitionUid, array $data, ?int $createdBy = null): EntityRecord
    {
        $definition = $this->definitions->require($definitionUid);
        $fields = $this->fields->forDefinition($definitionUid);
        $this->validate($fields, $data);

        $record = EntityRecord::create($definition->organizationId, $definitionUid, $data, $createdBy);
        $this->records->save($record);

        return $record;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $recordUid, array $data): EntityRecord
    {
        $record = $this->records->require($recordUid);
        $fields = $this->fields->forDefinition($record->definitionUid);
        $this->validate($fields, $data);

        $record->data = $data;
        $record->updatedAt = new \DateTimeImmutable();
        $this->records->save($record);

        return $record;
    }

    /**
     * @param EntityField[] $fields
     * @param array<string, mixed> $data
     */
    private function validate(array $fields, array $data): void
    {
        $byKey = [];
        foreach ($fields as $field) {
            $byKey[$field->fieldKey] = $field;
        }

        foreach (array_keys($data) as $key) {
            if (!isset($byKey[$key])) {
                throw new InvalidArgumentException("\"{$key}\" is not a declared field on this entity.");
            }
        }

        foreach ($byKey as $key => $field) {
            $value = $data[$key] ?? null;

            if ($field->required && $value === null) {
                throw new InvalidArgumentException("\"{$key}\" is required.");
            }

            if ($value !== null && !$this->matchesType($field->fieldType, $value)) {
                throw new InvalidArgumentException("\"{$key}\" must be of type \"{$field->fieldType}\".");
            }
        }
    }

    private function matchesType(string $fieldType, mixed $value): bool
    {
        return match ($fieldType) {
            'string' => is_string($value),
            'int' => is_int($value),
            'decimal' => is_int($value) || is_float($value),
            'bool' => is_bool($value),
            'date', 'datetime' => is_string($value) && strtotime($value) !== false,
            default => false,
        };
    }
}
