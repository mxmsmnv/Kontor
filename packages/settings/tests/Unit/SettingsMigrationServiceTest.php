<?php

declare(strict_types=1);

namespace Kontor\Settings\Tests\Unit;

use Kontor\Settings\Application\SettingsMigrationService;
use Kontor\Settings\Application\SettingsProviderRegistry;
use Kontor\Settings\Contracts\SettingsProviderInterface;
use Kontor\Settings\DTO\ProviderMigrationResult;
use PHPUnit\Framework\TestCase;

final class SettingsMigrationServiceTest extends TestCase
{
    public function testPreviewThenApply(): void
    {
        $provider = new TestSettingsProvider(['timezone' => 'UTC']);
        $registry = new SettingsProviderRegistry();
        $registry->register($provider);
        $service = new SettingsMigrationService($registry);
        $profile = $service->exportProfile();
        $profile['providers']['test']['data']['timezone'] = 'America/Los_Angeles';

        self::assertSame(1, $service->preview($profile)->changeCount());
        self::assertSame('UTC', $provider->settings['timezone']);

        self::assertTrue($service->apply($profile)->successful());
        self::assertSame('America/Los_Angeles', $provider->settings['timezone']);
    }

    public function testUnknownProviderDoesNotRequireOptionalComponent(): void
    {
        $provider = new TestSettingsProvider(['timezone' => 'UTC']);
        $registry = new SettingsProviderRegistry();
        $registry->register($provider);
        $service = new SettingsMigrationService($registry);
        $profile = $service->exportProfile();
        $profile['providers']['optional'] = ['schemaVersion' => 1, 'data' => ['enabled' => true]];

        $report = $service->preview($profile);

        self::assertTrue($report->successful());
        self::assertCount(1, $report->warnings);
    }

    public function testSensitiveKeysAreBlocked(): void
    {
        $provider = new TestSettingsProvider(['api_key' => 'secret']);
        $registry = new SettingsProviderRegistry();
        $registry->register($provider);

        $this->expectException(\InvalidArgumentException::class);
        (new SettingsMigrationService($registry))->exportProfile();
    }
}

final class TestSettingsProvider implements SettingsProviderInterface
{
    /** @param array<string, mixed> $settings */
    public function __construct(public array $settings)
    {
    }

    public function key(): string
    {
        return 'test';
    }

    public function label(): string
    {
        return 'Test settings';
    }

    public function export(): array
    {
        return $this->settings;
    }

    public function preview(array $settings): ProviderMigrationResult
    {
        $changes = $settings === $this->settings ? [] : [[
            'field' => 'timezone',
            'label' => 'Timezone',
            'from' => $this->settings['timezone'] ?? null,
            'to' => $settings['timezone'] ?? null,
        ]];

        return new ProviderMigrationResult('test', 'Test settings', $changes === [] ? 'unchanged' : 'ready', $changes);
    }

    public function apply(array $settings): ProviderMigrationResult
    {
        $preview = $this->preview($settings);
        $this->settings = $settings;

        return new ProviderMigrationResult('test', 'Test settings', 'applied', $preview->changes);
    }
}
