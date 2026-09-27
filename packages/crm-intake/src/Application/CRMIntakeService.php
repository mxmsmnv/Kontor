<?php

declare(strict_types=1);

namespace Kontor\CRMIntake\Application;

use InvalidArgumentException;
use Kontor\CRMIntake\Domain\IntakeProfile;
use Kontor\CRMIntake\Domain\IntakeResponse;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeProfileRepository;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeResponseRepository;

final class CRMIntakeService
{
    public const ENTITY_TYPES = ['contact', 'lead', 'deal'];
    public const FIELD_TYPES = ['text', 'textarea', 'url', 'select', 'multiselect', 'datetime'];

    public function __construct(
        private readonly IntakeProfileRepository $profiles,
        private readonly IntakeResponseRepository $responses,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     */
    public function configureDefault(
        string $organizationUid,
        string $name,
        array $fields,
        ?int $createdBy = null,
    ): IntakeProfile {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 191) {
            throw new InvalidArgumentException('CRM intake profile name is required and must not exceed 191 characters.');
        }
        $fields = $this->validateProfile($fields);
        $existing = $this->profiles->defaultForOrganization($organizationUid);
        $profile = $existing ?? IntakeProfile::create(
            $organizationUid,
            $name,
            $fields,
            true,
            $createdBy,
        );
        $profile->name = $name;
        $profile->fields = $fields;
        $profile->isDefault = true;
        $profile->status = 'active';
        $this->profiles->save($profile);

        return $profile;
    }

    /** @return array<int, array<string, mixed>> */
    public function fieldsFor(string $organizationUid, string $entityType): array
    {
        $this->entityType($entityType);
        $profile = $this->profiles->defaultForOrganization($organizationUid);
        if ($profile === null) {
            return [];
        }

        return array_values(array_filter(
            $profile->fields,
            static fn (array $field): bool => in_array($entityType, $field['targets'], true),
        ));
    }

    /** @return array<string, mixed> */
    public function valuesFor(string $organizationUid, string $entityType, string $entityUid): array
    {
        $this->entityType($entityType);

        return $this->responses->find($organizationUid, $entityType, $entityUid)?->answers ?? [];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validateAnswers(string $organizationUid, string $entityType, array $input): array
    {
        $fields = $this->fieldsFor($organizationUid, $entityType);
        $answers = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = $input[$key] ?? null;
            $normalized = $this->normalizeValue($field, $value);
            if ($field['required'] && ($normalized === null || $normalized === '' || $normalized === [])) {
                throw new InvalidArgumentException("{$field['label']} is required.");
            }
            if ($normalized !== null && $normalized !== '' && $normalized !== []) {
                $answers[$key] = $normalized;
            }
        }

        return $answers;
    }

    /** @param array<string, mixed> $answers */
    public function saveAnswers(
        string $organizationUid,
        string $entityType,
        string $entityUid,
        array $answers,
    ): IntakeResponse {
        $this->entityType($entityType);
        $profile = $this->profiles->defaultForOrganization($organizationUid)
            ?? throw new InvalidArgumentException('No active CRM intake profile is configured.');
        $answers = $this->validateAnswers($organizationUid, $entityType, $answers);
        $response = $this->responses->find($organizationUid, $entityType, $entityUid)
            ?? IntakeResponse::create(
                $organizationUid,
                $profile->uid->toString(),
                $entityType,
                $entityUid,
                $answers,
            );
        $response->answers = $answers;
        $this->responses->save($response);

        return $response;
    }

    public function copyAnswers(
        string $organizationUid,
        string $sourceType,
        string $sourceUid,
        string $targetType,
        string $targetUid,
    ): ?IntakeResponse {
        $source = $this->responses->find($organizationUid, $this->entityType($sourceType), $sourceUid);
        if ($source === null) {
            return null;
        }

        $targetFields = array_column($this->fieldsFor($organizationUid, $this->entityType($targetType)), null, 'key');
        $answers = array_intersect_key($source->answers, $targetFields);

        return $this->saveAnswers($organizationUid, $targetType, $targetUid, $answers);
    }

    /**
     * @param array<int, array<string, mixed>> $fields
     * @return array<int, array<string, mixed>>
     */
    public function validateProfile(array $fields): array
    {
        if ($fields === [] || count($fields) > 40) {
            throw new InvalidArgumentException('An intake profile needs between 1 and 40 fields.');
        }
        $normalized = [];
        $keys = [];
        foreach ($fields as $position => $field) {
            if (!is_array($field)) {
                throw new InvalidArgumentException('Every intake field must be an object.');
            }
            $key = strtolower(trim((string) ($field['key'] ?? '')));
            $label = trim((string) ($field['label'] ?? ''));
            $type = strtolower(trim((string) ($field['type'] ?? 'text')));
            $targets = array_values(array_unique(array_map('strval', (array) ($field['targets'] ?? []))));
            if (preg_match('/^[a-z][a-z0-9_]{1,63}$/', $key) !== 1 || isset($keys[$key])) {
                throw new InvalidArgumentException("Intake field key \"{$key}\" is invalid or duplicated.");
            }
            if ($label === '' || mb_strlen($label) > 120 || !in_array($type, self::FIELD_TYPES, true)) {
                throw new InvalidArgumentException("Intake field \"{$key}\" has an invalid label or type.");
            }
            if ($targets === [] || array_diff($targets, self::ENTITY_TYPES) !== []) {
                throw new InvalidArgumentException("Intake field \"{$key}\" has invalid targets.");
            }
            $options = [];
            foreach ((array) ($field['options'] ?? []) as $value => $optionLabel) {
                if (is_int($value) && is_array($optionLabel)) {
                    $value = (string) ($optionLabel['value'] ?? '');
                    $optionLabel = (string) ($optionLabel['label'] ?? '');
                }
                $value = strtolower(trim((string) $value));
                $optionLabel = trim((string) $optionLabel);
                if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $value) !== 1 || $optionLabel === '') {
                    throw new InvalidArgumentException("Intake field \"{$key}\" has an invalid option.");
                }
                $options[$value] = mb_substr($optionLabel, 0, 120);
            }
            if (in_array($type, ['select', 'multiselect'], true) && $options === []) {
                throw new InvalidArgumentException("Intake field \"{$key}\" needs selectable options.");
            }
            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'targets' => $targets,
                'options' => $options,
                'required' => (bool) ($field['required'] ?? false),
                'group' => mb_substr(trim((string) ($field['group'] ?? 'Qualification')), 0, 80),
                'description' => mb_substr(trim((string) ($field['description'] ?? '')), 0, 240),
                'note' => mb_substr(trim((string) ($field['note'] ?? '')), 0, 240),
                'binding' => in_array(($binding = strtolower(trim((string) ($field['binding'] ?? '')))), ['source'], true)
                    ? $binding
                    : null,
                'sort' => (int) ($field['sort'] ?? (($position + 1) * 10)),
            ];
            $keys[$key] = true;
        }
        usort($normalized, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        return $normalized;
    }

    /** @param array<string, mixed> $field */
    private function normalizeValue(array $field, mixed $value): mixed
    {
        if ($field['type'] === 'multiselect') {
            $values = array_values(array_unique(array_filter(array_map(
                static fn (mixed $item): string => strtolower(trim((string) $item)),
                is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]),
            ))));
            foreach ($values as $item) {
                if (!array_key_exists($item, $field['options'])) {
                    throw new InvalidArgumentException("{$field['label']} contains an unsupported option.");
                }
            }

            return $values;
        }
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if ($field['type'] === 'select') {
            $value = strtolower($value);
            if (!array_key_exists($value, $field['options'])) {
                throw new InvalidArgumentException("{$field['label']} contains an unsupported option.");
            }
        }
        if ($field['type'] === 'url'
            && (filter_var($value, FILTER_VALIDATE_URL) === false
                || !in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true))) {
            throw new InvalidArgumentException("{$field['label']} must be a valid HTTP or HTTPS URL.");
        }
        if ($field['type'] === 'datetime') {
            try {
                $value = (new \DateTimeImmutable($value))->format('Y-m-d\TH:i');
            } catch (\Throwable) {
                throw new InvalidArgumentException("{$field['label']} must be a valid date and time.");
            }
        }

        return mb_substr($value, 0, $field['type'] === 'textarea' ? 10_000 : 500);
    }

    private function entityType(string $entityType): string
    {
        $entityType = strtolower(trim($entityType));
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException("CRM intake entity type \"{$entityType}\" is not supported.");
        }

        return $entityType;
    }
}
