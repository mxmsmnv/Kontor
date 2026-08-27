<?php

declare(strict_types=1);

namespace Kontor\Settings\Contracts;

use Kontor\Settings\DTO\ProviderMigrationResult;

interface SettingsProviderInterface
{
    public function key(): string;

    public function label(): string;

    /** @return array<string, mixed> */
    public function export(): array;

    /** @param array<string, mixed> $settings */
    public function preview(array $settings): ProviderMigrationResult;

    /** @param array<string, mixed> $settings */
    public function apply(array $settings): ProviderMigrationResult;
}
