<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Germany\Application\GermanyLocalizationProvider;
use Kontor\Germany\Application\StandardChartOfAccountsSeeder;
use Kontor\Germany\Application\XRechnungFormatter;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\SDK\Contracts\LocalizationProviderInterface;

/**
 * KontorGermany bootstrap module (kontor.md Substage 9.4, fifth and
 * final component of Stage 9, and of the entire kontor.md#36 build plan).
 * Depends on kontor/core, kontor/sdk, and kontor/ledger (to seed its own
 * localized chart of accounts). Registers itself as the
 * `"localization.de"` capability in Core's `CapabilityRegistry` — the
 * "localization contracts" milestone's whole point is that neither Core
 * nor any business package ever depends on this class directly.
 */
class KontorGermany extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Germany',
            'summary' => 'Localization contracts, Germany package, country-specific document formats.',
            'version' => '002',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorGermany',
            'icon' => 'flag',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorLedger'],
            'permissions' => [
                'kontor-germany-view' => 'View Germany localization tools',
                'kontor-germany-configure' => 'Seed and configure Germany localization',
            ],
        ];
    }

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));

        $kontor->container()->get(CapabilityRegistry::class)->register(
            capability: 'localization.de',
            version: '1.0',
            contract: LocalizationProviderInterface::class,
            implementation: $this->localizationProvider(),
            component: 'KontorGermany',
        );
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__."/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorGermany', $language, $strings);
        }
    }

    public function localizationProvider(): GermanyLocalizationProvider
    {
        return new GermanyLocalizationProvider();
    }

    public function chartOfAccountsSeeder(): StandardChartOfAccountsSeeder
    {
        /** @var KontorLedger $ledgerModule */
        $ledgerModule = $this->wire()->modules->get('KontorLedger');

        return new StandardChartOfAccountsSeeder(new ChartOfAccountsService($ledgerModule->accountRepository()));
    }

    public function documentFormatter(): XRechnungFormatter
    {
        return new XRechnungFormatter();
    }

    public function ___install(): void
    {
        $components = new ComponentRegistry($this->wire()->database->pdo());
        $components->markInstalled('germany', self::getModuleInfo()['version'], 'germany');
        $components->enable('germany');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        foreach (self::getModuleInfo()['permissions'] as $name => $title) {
            $permission = $this->wire()->permissions->get($name);
            if ($permission->id) {
                continue;
            }
            $permission = $this->wire()->permissions->add($name);
            $permission->title = $title;
            $this->wire()->permissions->save($permission);
        }

        $components = new ComponentRegistry($this->wire()->database->pdo());
        $components->markInstalled('germany', self::getModuleInfo()['version'], 'germany');
        $components->enable('germany');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data. This
     * package has no data of its own — nothing to persist.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Germany module removed.'));
    }
}
