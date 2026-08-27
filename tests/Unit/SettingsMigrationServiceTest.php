<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit;

use Kontor\Settings\Application\SettingsMigrationService;
use Kontor\Settings\Application\SettingsProviderRegistry;
use Kontor\Settings\Contracts\SettingsProviderInterface;
use Kontor\Settings\DTO\ProviderMigrationResult;
use PHPUnit\Framework\TestCase;

final class SettingsMigrationServiceTest extends TestCase
{
    public function testPreviewDoesNotMutateAndApplyDoes(): void
    {
        $provider = new InMemorySettingsProvider(['name' => 'Before']);
        $service = $this->service($provider);
        $profile = $service->exportProfile(['host' => 'source.test']);
        $profile['providers']['organization']['data']['name'] = 'After';

        $preview = $service->preview($profile);

        self::assertTrue($preview->successful());
        self::assertSame(1, $preview->changeCount());
        self::assertSame(['name' => 'Before'], $provider->settings);

        $applied = $service->apply($profile);

        self::assertTrue($applied->successful());
        self::assertTrue($applied->applied);
        self::assertSame(['name' => 'After'], $provider->settings);
    }

    public function testUnavailableProviderIsSkippedWithWarning(): void
    {
        $provider = new InMemorySettingsProvider(['name' => 'Kontor']);
        $service = $this->service($provider);
        $profile = $service->exportProfile();
        $profile['providers']['optional-component'] = [
            'schemaVersion' => 1,
            'data' => ['enabled' => true],
        ];

        $report = $service->preview($profile);

        self::assertTrue($report->successful());
        self::assertCount(1, $report->warnings);
        self::assertStringContainsString('not available', $report->warnings[0]);
    }

    public function testSensitiveSettingsAreRejectedOnExportAndImport(): void
    {
        $provider = new InMemorySettingsProvider(['apiToken' => 'do-not-export']);
        $service = $this->service($provider);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sensitive setting');
        $service->exportProfile();
    }

    public function testInvalidSchemaAndSensitiveImportedKeyAreBlocked(): void
    {
        $provider = new InMemorySettingsProvider(['name' => 'Kontor']);
        $service = $this->service($provider);
        $profile = $service->exportProfile();
        $profile['schemaVersion'] = 2;
        $profile['providers']['organization']['data']['password'] = 'unsafe';

        $report = $service->preview($profile);

        self::assertFalse($report->successful());
        self::assertCount(2, $report->errors);
    }

    public function testDecodeRejectsInvalidAndOversizedJson(): void
    {
        $service = $this->service(new InMemorySettingsProvider(['name' => 'Kontor']));

        foreach (['{broken', str_repeat('x', SettingsMigrationService::MAX_JSON_BYTES + 1)] as $json) {
            try {
                $service->decode($json);
                self::fail('Invalid profile was accepted.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private function service(InMemorySettingsProvider $provider): SettingsMigrationService
    {
        $registry = new SettingsProviderRegistry();
        $registry->register($provider);

        return new SettingsMigrationService($registry);
    }
}

final class InMemorySettingsProvider implements SettingsProviderInterface
{
    /** @param array<string, mixed> $settings */
    public function __construct(public array $settings)
    {
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
        return $this->settings;
    }

    public function preview(array $settings): ProviderMigrationResult
    {
        $changes = [];
        foreach ($settings as $field => $value) {
            if (($this->settings[$field] ?? null) === $value) {
                continue;
            }
            $changes[] = [
                'field' => (string) $field,
                'label' => ucfirst((string) $field),
                'from' => $this->settings[$field] ?? null,
                'to' => $value,
            ];
        }

        return new ProviderMigrationResult(
            key: $this->key(),
            label: $this->label(),
            status: $changes === [] ? 'unchanged' : 'ready',
            changes: $changes,
        );
    }

    public function apply(array $settings): ProviderMigrationResult
    {
        $preview = $this->preview($settings);
        $this->settings = $settings;

        return new ProviderMigrationResult(
            key: $this->key(),
            label: $this->label(),
            status: 'applied',
            changes: $preview->changes,
        );
    }
}
