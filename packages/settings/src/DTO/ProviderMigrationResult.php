<?php

declare(strict_types=1);

namespace Kontor\Settings\DTO;

final readonly class ProviderMigrationResult
{
    /**
     * @param array<int, array{field: string, label: string, from: mixed, to: mixed}> $changes
     * @param string[] $warnings
     * @param string[] $errors
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public array $changes = [],
        public array $warnings = [],
        public array $errors = [],
    ) {
    }

    public function successful(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'status' => $this->status,
            'changes' => $this->changes,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
        ];
    }
}
