<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Expenses\Application\ExpenseWorkflowService;
use Kontor\Expenses\Application\ExpenseWorkflowCoordinator;
use Kontor\Expenses\Application\LedgerExpensePostingService;
use Kontor\Expenses\Health\ExpensesHealthCheck;
use Kontor\Expenses\Infrastructure\Persistence\CategoryRepository;
use Kontor\Expenses\Infrastructure\Persistence\ExpenseRepository;
use Kontor\Expenses\Migrations\Migration0001CreateExpenseCategoriesTable;
use Kontor\Expenses\Migrations\Migration0002CreateExpensesTable;

/**
 * KontorExpenses bootstrap module (kontor.md Substage 6.3). Third
 * component of Stage 6. Depends only on kontor/core — supplier_uid and
 * receipt_file_uid both stay loose references (kontor.md#10.7), no hard
 * dependency on kontor/purchasing or kontor/files.
 */
class KontorExpenses extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Expenses',
            'summary' => 'Expenses, categories, receipts, approvals.',
            'version' => '004',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorExpenses',
            'icon' => 'money',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-expenses-category-view' => 'View expense categories',
                'kontor-expenses-category-manage' => 'Manage expense categories',
                'kontor-expenses-expense-view' => 'View expenses',
                'kontor-expenses-expense-create' => 'Create expenses',
                'kontor-expenses-expense-edit-draft' => 'Edit draft expenses',
                'kontor-expenses-expense-submit' => 'Submit expenses for approval',
                'kontor-expenses-expense-approve' => 'Approve or reject expenses',
                'kontor-expenses-expense-reimburse' => 'Mark expenses reimbursed',
            ],
        ];
    }

    private ?CategoryRepository $categoryRepository = null;
    private ?ExpenseRepository $expenseRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorExpenses', $language, $strings);
        }
    }

    public function categoryRepository(): CategoryRepository
    {
        return $this->categoryRepository ??= new CategoryRepository($this->pdo(), $this->organizations());
    }

    public function expenseRepository(): ExpenseRepository
    {
        return $this->expenseRepository ??= new ExpenseRepository($this->pdo(), $this->organizations());
    }

    public function workflow(): ExpenseWorkflowService
    {
        $posting = null;
        if ($this->wire()->modules->isInstalled('KontorLedger')) {
            /** @var KontorLedger $ledger */
            $ledger = $this->wire()->modules->get('KontorLedger');
            $posting = new LedgerExpensePostingService(
                $ledger->accountRepository(),
                $ledger->entryRepository(),
                $ledger->entries(),
            );
        }

        return new ExpenseWorkflowService($this->expenseRepository(), $posting);
    }

    public function workflowCoordinator(): ?ExpenseWorkflowCoordinator
    {
        if (!$this->wire()->modules->isInstalled('KontorWorkflow')) {
            return null;
        }

        /** @var KontorWorkflow $workflow */
        $workflow = $this->wire()->modules->get('KontorWorkflow');

        return new ExpenseWorkflowCoordinator(
            $this->expenseRepository(),
            $this->workflow(),
            $workflow->definitionRepository(),
            $workflow->definitions(),
            $workflow->instanceRepository(),
            $workflow->engine(),
        );
    }

    public function healthCheck(): ExpensesHealthCheck
    {
        return new ExpensesHealthCheck($this->pdo());
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
            new Migration0001CreateExpenseCategoriesTable(),
            new Migration0002CreateExpensesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('expenses', self::getModuleInfo()['version'], 'expenses');
        $components->enable('expenses');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('expenses', self::getModuleInfo()['version'], 'expenses');
        $components->enable('expenses');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Expenses module removed. Expense data was kept intact.'));
    }
}
