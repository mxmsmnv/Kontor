<?php

declare(strict_types=1);

namespace Kontor\Settings\Contracts;

use Kontor\Settings\DTO\SettingsMigrationReport;

interface SettingsMigrationInterface
{
    /** @param array<string, mixed> $source */
    public function exportProfile(array $source = []): array;

    /** @return array<string, mixed> */
    public function decode(string $json): array;

    /** @param array<string, mixed> $profile */
    public function preview(array $profile): SettingsMigrationReport;

    /** @param array<string, mixed> $profile */
    public function apply(array $profile): SettingsMigrationReport;
}
