<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Ledger\Application\AccountBalanceService;
use Kontor\Ledger\Application\ChartOfAccountsService;
use Kontor\Ledger\Application\LedgerEntryService;
use Kontor\Ledger\Health\LedgerHealthCheck;
use Kontor\Ledger\Infrastructure\Persistence\AccountRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerEntryRepository;
use Kontor\Ledger\Infrastructure\Persistence\LedgerLineRepository;
use Kontor\Ledger\Migrations\Migration0001CreateAccountsTable;
use Kontor\Ledger\Migrations\Migration0002CreateEntriesTable;
use Kontor\Ledger\Migrations\Migration0003CreateLinesTable;

/**
 * KontorLedger bootstrap module (kontor.md Substage 9.4, fourth
 * component of Stage 9). Depends only on kontor/core. Country packages
 * (e.g. kontor/germany) depend on this one to seed their own localized
 * chart of accounts via ChartOfAccountsService — this package itself has
 * no knowledge of any specific country's chart.
 */
class KontorLedger extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Ledger',
            'summary' => 'Double-entry foundations, chart of accounts.',
            'version' => '002',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorLedger',
            'icon' => 'balance-scale',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-ledger-account-manage' => 'Create and manage chart-of-accounts accounts',
                'kontor-ledger-entry-view' => 'View ledger entries',
                'kontor-ledger-entry-record' => 'Record ledger entries',
            ],
        ];
    }

    private ?AccountRepository $accountRepository = null;
    private ?LedgerEntryRepository $entryRepository = null;
    private ?LedgerLineRepository $lineRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__."/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorLedger', $language, $strings);
        }
    }

    public function accountRepository(): AccountRepository
    {
        return $this->accountRepository ??= new AccountRepository($this->pdo(), $this->organizations());
    }

    public function entryRepository(): LedgerEntryRepository
    {
        return $this->entryRepository ??= new LedgerEntryRepository($this->pdo(), $this->organizations());
    }

    public function lineRepository(): LedgerLineRepository
    {
        return $this->lineRepository ??= new LedgerLineRepository($this->pdo(), $this->organizations());
    }

    public function chartOfAccounts(): ChartOfAccountsService
    {
        return new ChartOfAccountsService($this->accountRepository());
    }

    public function entries(): LedgerEntryService
    {
        return new LedgerEntryService($this->accountRepository(), $this->entryRepository(), $this->lineRepository());
    }

    public function balances(): AccountBalanceService
    {
        return new AccountBalanceService($this->accountRepository(), $this->lineRepository());
    }

    public function healthCheck(): LedgerHealthCheck
    {
        return new LedgerHealthCheck($this->pdo());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateAccountsTable(),
            new Migration0002CreateEntriesTable(),
            new Migration0003CreateLinesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('ledger', self::getModuleInfo()['version'], 'ledger');
        $components->enable('ledger');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('ledger', self::getModuleInfo()['version'], 'ledger');
        $components->enable('ledger');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Ledger module removed. Accounts and ledger entries were kept intact.'));
    }
}
