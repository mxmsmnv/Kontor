<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeImmutable;
use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\SDK\ValueObjects\Money;
use PDO;
use ProcessWire\KontorExpenses;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait ExpenseTools
{
    private function mcpExpenseTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorExpenses')) {
            return [];
        }

        $offset = ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000];
        $uid = $this->uidSchema();

        return [
            $this->tool('kontor_expense_categories_list', 'List active expense categories',
                'Find an expense category UID in the linked user’s organization.',
                $this->mcpKontorExpenseCategoriesList(...), 'read', ['offset' => $offset]),
            $this->tool('kontor_expenses_list', 'List expenses', 'List organization expenses the linked user may view.',
                $this->mcpKontorExpensesList(...), 'read', [
                    'offset' => $offset,
                    'status' => ['type' => 'string', 'enum' => ['draft', 'submitted', 'approved', 'rejected', 'reimbursed', 'cancelled']],
                ]),
            $this->tool('kontor_expense_get', 'Get an expense', 'Read one organization expense by UID.',
                $this->mcpKontorExpenseGet(...), 'read', ['uid' => $uid], ['uid']),
            $this->tool('kontor_expense_draft_create', 'Record a draft expense',
                'Save a reviewed draft expense with a positive amount in minor units. This leaves the expense in draft.',
                $this->mcpKontorExpenseDraftCreate(...), 'draft', [
                    'category_uid' => $uid,
                    'description' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                    'amount_minor' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 999999999999],
                    'currency' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$'],
                    'expense_date' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}$'],
                    'supplier_uid' => $uid,
                    'receipt_file_uid' => $uid,
                    'idempotency_key' => $this->idempotencySchema(),
                ], ['category_uid', 'description', 'amount_minor', 'currency', 'expense_date', 'idempotency_key']),
            $this->tool('kontor_expense_reviewed_action', 'Submit, approve or reject an expense',
                'Apply the reviewed Workspace transition. Review the record and exact action with the user first.',
                $this->mcpKontorExpenseReviewedAction(...), 'draft', [
                    'uid' => $uid,
                    'action' => ['type' => 'string', 'enum' => ['submit', 'approve', 'reject']],
                    'reason' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                    'confirmation' => ['type' => 'string', 'const' => 'APPLY_REVIEWED_EXPENSE_ACTION'],
                    'idempotency_key' => $this->idempotencySchema(),
                ], ['uid', 'action', 'confirmation', 'idempotency_key']),
        ];
    }

    public function mcpKontorExpenseCategoriesList(int $offset = 0): array
    {
        [$module, , $organization] = $this->mcpExpenseContext('category-view');
        $categories = $module->categoryRepository()->activeForOrganization($organization, 26, $offset);
        $rows = array_map(static fn (ExpenseCategory $category): array => [
            'uid' => $category->uid->toString(), 'name' => $category->name, 'code' => $category->code,
        ], $categories);

        return $this->mcpExpensePage($rows, $offset);
    }

    public function mcpKontorExpensesList(?string $status = null, int $offset = 0): array
    {
        [$module, $actor, $organization] = $this->mcpExpenseContext('expense-view');
        $expenses = $module->expenseRepository()->forOrganization($organization, $status, 26, $offset);
        $rows = array_map(fn (Expense $expense): array => $this->mcpExpenseRecord($expense, $actor), $expenses);

        return $this->mcpExpensePage($rows, $offset);
    }

    public function mcpKontorExpenseGet(string $uid): array
    {
        [$module, $actor, $organization] = $this->mcpExpenseContext('expense-view');
        $expense = $module->expenseRepository()->findActive($organization, $uid);
        if ($expense === null) {
            throw new Wire404Exception('Expense not found.');
        }

        return ['record' => $this->mcpExpenseRecord($expense, $actor)];
    }

    public function mcpKontorExpenseDraftCreate(
        string $category_uid,
        string $description,
        int $amount_minor,
        string $currency,
        string $expense_date,
        string $idempotency_key,
        ?string $supplier_uid = null,
        ?string $receipt_file_uid = null,
    ): array {
        [$module, $actor, $organization] = $this->mcpExpenseContext('expense-create');
        if (!$this->mcpExpensePermission($actor, 'kontor-expenses-category-view')) {
            throw new WirePermissionException('Expense category view permission is required.');
        }
        $description = trim($this->wire()->sanitizer->text($description, ['maxLength' => 500]));
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $expense_date);
        if ($description === '' || $amount_minor < 1 || $amount_minor > 999999999999
            || preg_match('/^[A-Z]{3}$/D', $currency) !== 1 || !$date || $date->format('Y-m-d') !== $expense_date
            || preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $idempotency_key) !== 1) {
            throw new WireException('Provide an expense description, positive amount, three-letter currency, valid date and idempotency key.');
        }

        if ($module->categoryRepository()->activeForOrganization($organization, 1, uid: $category_uid) === []) {
            throw new Wire404Exception('Active expense category not found.');
        }
        $modules = $this->wire()->modules;
        if ($supplier_uid !== null) {
            if (!$modules->isInstalled('KontorPurchasing') || !$this->mcpExpensePermission($actor, 'kontor-purchasing-supplier-view')) {
                throw new WirePermissionException('Supplier view permission is required.');
            }
            $supplier = $modules->get('KontorPurchasing')->supplierRepository()->find($supplier_uid, activeOnly: true);
            if ($supplier === null || !hash_equals($organization, $supplier->organizationId) || !$supplier->isActive()) {
                throw new Wire404Exception('Active supplier not found.');
            }
        }
        if ($receipt_file_uid !== null && !$this->mcpExpenseReceiptVisible($receipt_file_uid, $organization, $actor)) {
            throw new Wire404Exception('Receipt file not found.');
        }

        $expense = Expense::create(
            $organization, $category_uid, $description, Money::ofMinor($amount_minor, $currency),
            $date, $supplier_uid, $receipt_file_uid, (int) $actor->id,
        );
        $arguments = [$category_uid, $description, $amount_minor, $currency, $expense_date, $supplier_uid, $receipt_file_uid];
        $key = 'mcp:expense:' . $actor->id . ':' . hash('sha256', $idempotency_key);
        $container = $modules->get('Kontor')->container();
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $pdo = $container->get(PDO::class);
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $result = $idempotency->remember($organization, $key, $arguments, function () use ($module, $expense, $container, $organization, $actor): array {
                $module->expenseRepository()->save($expense);
                $uid = $expense->uid->toString();
                $container->get(AuditLogger::class)->record(
                    $container->get(OrganizationRepository::class)->internalIdOf($organization),
                    'expenses', 'expense', $uid, 'created', 'user', (string) $actor->id, metadata: ['transport' => 'mcp'],
                );

                return ['status' => 201, 'body' => ['uid' => $uid]];
            });
            if ($module->expenseRepository()->findActive($organization, $result->body['uid']) === null) {
                throw new Wire404Exception('Expense not found.');
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $result->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof WireException) {
                throw $error;
            }

            return $this->safe(static fn () => throw $error);
        }
    }

    public function mcpKontorExpenseReviewedAction(
        string $uid,
        string $action,
        string $confirmation,
        string $idempotency_key,
        ?string $reason = null,
    ): array {
        if ($confirmation !== 'APPLY_REVIEWED_EXPENSE_ACTION' || !in_array($action, ['submit', 'approve', 'reject'], true)
            || preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $idempotency_key) !== 1) {
            throw new WireException('Provide a supported reviewed expense action, confirmation and idempotency key.');
        }
        [$module, $actor, $organization] = $this->mcpExpenseContext('expense-view');
        $permission = $action === 'submit' ? 'kontor-expenses-expense-submit' : 'kontor-expenses-expense-approve';
        if (!$this->mcpExpensePermission($actor, $permission)) {
            throw new WirePermissionException('The linked user lacks permission for this expense action.');
        }
        $reason = trim($this->wire()->sanitizer->text($reason ?? '', ['maxLength' => 500]));
        if (($action === 'reject' && $reason === '') || ($action !== 'reject' && $reason !== '')) {
            throw new WireException('Supply a rejection reason only when rejecting an expense.');
        }

        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $pdo = $container->get(PDO::class);
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $expense = $module->expenseRepository()->findActive($organization, $uid, forUpdate: true);
            if ($expense === null) {
                throw new Wire404Exception('Expense not found.');
            }
            $result = $idempotency->remember(
                $organization,
                'mcp:expense-action:' . $actor->id . ':' . hash('sha256', $idempotency_key),
                [$uid, $action, $reason],
                function () use ($module, $actor, $organization, $container, $uid, $action, $reason, $expense): array {
                    $expected = $action === 'submit' ? 'draft' : 'submitted';
                    if ($expense->status !== $expected) {
                        throw new WireException('This expense action requires status ' . $expected . '.');
                    }
                    $actorId = (int) $actor->id;
                    $coordinator = $module->workflowCoordinator();
                    if ($coordinator !== null) {
                        $expense = $coordinator->perform($uid, $action, $actorId, $reason);
                    } else {
                        $workflow = $module->workflow();
                        $expense = match ($action) {
                            'submit' => $workflow->submit($uid, $actorId),
                            'approve' => $workflow->approve($uid, $actorId),
                            'reject' => $workflow->reject($uid, $actorId, $reason),
                        };
                    }
                    $container->get(AuditLogger::class)->record(
                        $container->get(OrganizationRepository::class)->internalIdOf($organization),
                        'expenses', 'expense', $uid, $action, 'user', (string) $actor->id,
                        current: ['status' => $expense->status, 'rejectionReason' => $expense->rejectionReason],
                        metadata: ['transport' => 'mcp'],
                    );

                    return ['status' => 200, 'body' => ['uid' => $uid, 'status' => $expense->status]];
                },
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $result->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof WireException) {
                throw $error;
            }

            return $this->safe(static fn () => throw $error);
        }
    }

    /** @return array{KontorExpenses, User, string} */
    private function mcpExpenseContext(string $permission): array
    {
        $actor = $this->mcpActor();
        $modules = $this->wire()->modules;
        if ($actor === null || !$this->mcpExpensePermission($actor, 'kontor-expenses-' . $permission)
            || !$modules->isInstalled('KontorExpenses')) {
            throw new WirePermissionException('The linked user lacks the required expense permission.');
        }
        $organization = $modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();

        return [$modules->get('KontorExpenses'), $actor, $organization];
    }

    private function mcpExpensePermission(User $actor, string $permission): bool
    {
        return $actor->isSuperuser() || $actor->hasPermission($permission);
    }

    private function mcpExpenseRecord(Expense $expense, User $actor): array
    {
        $receipt = $expense->receiptFileUid;

        return [
            'uid' => $expense->uid->toString(),
            'description' => $expense->description,
            'amount_minor' => $expense->amount->amountMinor(),
            'currency' => $expense->amount->currencyCode(),
            'expense_date' => $expense->expenseDate->format('Y-m-d'),
            'status' => $expense->status,
            'category_uid' => $this->mcpExpensePermission($actor, 'kontor-expenses-category-view') ? $expense->categoryUid : null,
            'supplier_uid' => $this->mcpExpensePermission($actor, 'kontor-purchasing-supplier-view') ? $expense->supplierUid : null,
            'receipt_file_uid' => $receipt !== null && $this->mcpExpenseReceiptVisible($receipt, $expense->organizationId, $actor) ? $receipt : null,
        ];
    }

    private function mcpExpenseReceiptVisible(string $uid, string $organization, User $actor): bool
    {
        if (!$this->wire()->modules->isInstalled('KontorFiles') || !$this->mcpExpensePermission($actor, 'kontor-files-file-view')) {
            return false;
        }
        try {
            [$file, $fileOrganization] = $this->mcpReadableFile($uid, 'kontor-files-file-view');
        } catch (Wire404Exception|WirePermissionException) {
            return false;
        }

        return $file['archived_at'] === null && hash_equals($organization, $fileOrganization);
    }

    private function mcpExpensePage(array $rows, int $offset): array
    {
        return [
            'items' => array_slice($rows, 0, 25),
            'next_offset' => count($rows) > 25 && $offset + 25 <= 10000 ? $offset + 25 : null,
        ];
    }
}
