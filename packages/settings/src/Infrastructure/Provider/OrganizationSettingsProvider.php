<?php

declare(strict_types=1);

namespace Kontor\Settings\Infrastructure\Provider;

use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Settings\Contracts\SettingsProviderInterface;
use Kontor\Settings\DTO\ProviderMigrationResult;

final class OrganizationSettingsProvider implements SettingsProviderInterface
{
    private const DATE_FORMATS = ['Y-m-d', 'd.m.Y', 'm/d/Y', 'd/m/Y'];

    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly string $organizationUid,
    ) {
    }

    public function key(): string
    {
        return 'organization';
    }

    public function label(): string
    {
        return 'Organization defaults';
    }

    public function export(): array
    {
        $organization = $this->organization();

        return [
            'name' => $organization->name,
            'legalName' => $organization->legalName,
            'countryCode' => $organization->countryCode,
            'defaultLanguage' => $organization->defaultLanguage,
            'defaultCurrency' => $organization->defaultCurrency,
            'timezone' => $organization->timezone,
            'dateFormat' => (string) ($organization->settings['dateFormat'] ?? 'Y-m-d'),
        ];
    }

    public function preview(array $settings): ProviderMigrationResult
    {
        [$normalized, $errors] = $this->validate($settings);
        $changes = $errors === [] ? $this->changes($this->export(), $normalized) : [];

        return new ProviderMigrationResult(
            key: $this->key(),
            label: $this->label(),
            status: $errors !== [] ? 'invalid' : ($changes === [] ? 'unchanged' : 'ready'),
            changes: $changes,
            errors: $errors,
        );
    }

    public function apply(array $settings): ProviderMigrationResult
    {
        $preview = $this->preview($settings);

        if (!$preview->successful() || $preview->changes === []) {
            return $preview;
        }

        [$normalized] = $this->validate($settings);
        $organization = $this->organization();
        $organization->name = $normalized['name'];
        $organization->legalName = $normalized['legalName'];
        $organization->countryCode = $normalized['countryCode'];
        $organization->defaultLanguage = $normalized['defaultLanguage'];
        $organization->defaultCurrency = $normalized['defaultCurrency'];
        $organization->timezone = $normalized['timezone'];
        $organization->settings['dateFormat'] = $normalized['dateFormat'];
        $this->organizations->save($organization);

        return new ProviderMigrationResult(
            key: $this->key(),
            label: $this->label(),
            status: 'applied',
            changes: $preview->changes,
        );
    }

    /** @return array{0: array<string, mixed>, 1: string[]} */
    private function validate(array $settings): array
    {
        $required = ['name', 'countryCode', 'defaultLanguage', 'defaultCurrency', 'timezone', 'dateFormat'];
        $errors = [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $settings) || !is_string($settings[$field]) || trim($settings[$field]) === '') {
                $errors[] = "Organization field \"{$field}\" is required.";
            }
        }

        $normalized = [
            'name' => trim((string) ($settings['name'] ?? '')),
            'legalName' => isset($settings['legalName']) && trim((string) $settings['legalName']) !== ''
                ? trim((string) $settings['legalName'])
                : null,
            'countryCode' => strtoupper(trim((string) ($settings['countryCode'] ?? ''))),
            'defaultLanguage' => trim((string) ($settings['defaultLanguage'] ?? '')),
            'defaultCurrency' => strtoupper(trim((string) ($settings['defaultCurrency'] ?? ''))),
            'timezone' => trim((string) ($settings['timezone'] ?? '')),
            'dateFormat' => trim((string) ($settings['dateFormat'] ?? '')),
        ];

        if (preg_match('/^[A-Z]{2}$/', $normalized['countryCode']) !== 1) {
            $errors[] = 'Organization countryCode must use a two-letter ISO code.';
        }
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $normalized['defaultLanguage']) !== 1) {
            $errors[] = 'Organization defaultLanguage must use a supported language code.';
        }
        if (preg_match('/^[A-Z]{3}$/', $normalized['defaultCurrency']) !== 1) {
            $errors[] = 'Organization defaultCurrency must use a three-letter ISO code.';
        }
        if (!in_array($normalized['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $errors[] = 'Organization timezone must be a valid IANA timezone.';
        }
        if (!in_array($normalized['dateFormat'], self::DATE_FORMATS, true)) {
            $errors[] = 'Organization dateFormat is not supported.';
        }

        return [$normalized, array_values(array_unique($errors))];
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     * @return array<int, array{field: string, label: string, from: mixed, to: mixed}>
     */
    private function changes(array $current, array $incoming): array
    {
        $labels = [
            'name' => 'Display name',
            'legalName' => 'Legal name',
            'countryCode' => 'Country',
            'defaultLanguage' => 'Language',
            'defaultCurrency' => 'Currency',
            'timezone' => 'Timezone',
            'dateFormat' => 'Date format',
        ];
        $changes = [];

        foreach ($labels as $field => $label) {
            if (($current[$field] ?? null) === ($incoming[$field] ?? null)) {
                continue;
            }
            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from' => $current[$field] ?? null,
                'to' => $incoming[$field] ?? null,
            ];
        }

        return $changes;
    }

    private function organization(): Organization
    {
        return $this->organizations->require($this->organizationUid);
    }
}
