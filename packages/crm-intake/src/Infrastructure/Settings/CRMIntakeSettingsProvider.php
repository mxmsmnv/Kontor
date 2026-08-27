<?php

declare(strict_types=1);

namespace Kontor\CRMIntake\Infrastructure\Settings;

use Kontor\CRMIntake\Application\CRMIntakeService;
use Kontor\CRMIntake\Infrastructure\Persistence\IntakeProfileRepository;
use Kontor\Settings\Contracts\SettingsProviderInterface;
use Kontor\Settings\DTO\ProviderMigrationResult;

final class CRMIntakeSettingsProvider implements SettingsProviderInterface
{
    public function __construct(
        private readonly string $organizationUid,
        private readonly IntakeProfileRepository $profiles,
        private readonly CRMIntakeService $service,
    ) {
    }

    public function key(): string
    {
        return 'crm_intake';
    }

    public function label(): string
    {
        return 'CRM intake profile';
    }

    public function export(): array
    {
        $profile = $this->profiles->defaultForOrganization($this->organizationUid);

        return $profile === null ? [] : ['name' => $profile->name, 'fields' => $profile->fields];
    }

    public function preview(array $settings): ProviderMigrationResult
    {
        try {
            $name = trim((string) ($settings['name'] ?? ''));
            if ($name === '') {
                throw new \InvalidArgumentException('CRM intake profile name is required.');
            }
            $fields = $this->service->validateProfile((array) ($settings['fields'] ?? []));
            $incoming = ['name' => $name, 'fields' => $fields];
            $current = $this->export();
            $changes = $current === $incoming ? [] : [[
                'field' => 'profile',
                'label' => 'CRM intake profile',
                'from' => $current === [] ? null : $current['name'],
                'to' => $name,
            ]];

            return new ProviderMigrationResult($this->key(), $this->label(), $changes === [] ? 'unchanged' : 'ready', $changes);
        } catch (\InvalidArgumentException $exception) {
            return new ProviderMigrationResult(
                $this->key(),
                $this->label(),
                'invalid',
                [],
                errors: [$exception->getMessage()],
            );
        }
    }

    public function apply(array $settings): ProviderMigrationResult
    {
        $preview = $this->preview($settings);
        if (!$preview->successful() || $preview->changes === []) {
            return $preview;
        }
        $this->service->configureDefault(
            $this->organizationUid,
            (string) $settings['name'],
            (array) $settings['fields'],
        );

        return new ProviderMigrationResult($this->key(), $this->label(), 'applied', $preview->changes);
    }
}
