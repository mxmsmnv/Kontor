<?php

declare(strict_types=1);

namespace Kontor\Expenses\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Expenses\Domain\Expense;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class ExpenseRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Expense
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_expenses WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Expense
    {
        return $this->find($id) ?? throw new RuntimeException("Expense \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Expense) {
            throw new InvalidArgumentException('ExpenseRepository::save() expects an Expense.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_expenses
                (uid, organization_id, category_uid, supplier_uid, description, amount_minor, currency_code,
                 expense_date, receipt_file_uid, status, submitted_by, approved_by, submitted_at, approved_at,
                 rejected_at, reimbursed_at, rejection_reason, created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :category_uid, :supplier_uid, :description, :amount_minor, :currency_code,
                 :expense_date, :receipt_file_uid, :status, :submitted_by, :approved_by, :submitted_at, :approved_at,
                 :rejected_at, :reimbursed_at, :rejection_reason, :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                supplier_uid = VALUES(supplier_uid), description = VALUES(description),
                amount_minor = VALUES(amount_minor), expense_date = VALUES(expense_date),
                receipt_file_uid = VALUES(receipt_file_uid), status = VALUES(status),
                submitted_by = VALUES(submitted_by), approved_by = VALUES(approved_by),
                submitted_at = VALUES(submitted_at), approved_at = VALUES(approved_at),
                rejected_at = VALUES(rejected_at), reimbursed_at = VALUES(reimbursed_at),
                rejection_reason = VALUES(rejection_reason), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'category_uid' => $entity->categoryUid,
            'supplier_uid' => $entity->supplierUid,
            'description' => $entity->description,
            'amount_minor' => $entity->amount->amountMinor(),
            'currency_code' => $entity->amount->currencyCode(),
            'expense_date' => $entity->expenseDate->format('Y-m-d'),
            'receipt_file_uid' => $entity->receiptFileUid,
            'status' => $entity->status,
            'submitted_by' => $entity->submittedBy,
            'approved_by' => $entity->approvedBy,
            'submitted_at' => $entity->submittedAt?->format('Y-m-d H:i:s.u'),
            'approved_at' => $entity->approvedAt?->format('Y-m-d H:i:s.u'),
            'rejected_at' => $entity->rejectedAt?->format('Y-m-d H:i:s.u'),
            'reimbursed_at' => $entity->reimbursedAt?->format('Y-m-d H:i:s.u'),
            'rejection_reason' => $entity->rejectionReason,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_expenses SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_expenses SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return Expense[]
     */
    public function forStatus(string $organizationUid, string $status): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_expenses WHERE organization_id = :organization_id AND status = :status AND archived_at IS NULL ORDER BY expense_date ASC'
        );
        $statement->execute(['organization_id' => $organizationId, 'status' => $status]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return Expense[]
     */
    public function forOrganization(string $organizationUid, ?string $status = null, int $limit = 100): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $limit = max(1, min(500, $limit));
        $statusClause = $status !== null ? ' AND status = :status' : '';
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_expenses
             WHERE organization_id = :organization_id
               AND archived_at IS NULL{$statusClause}
             ORDER BY expense_date DESC, created_at DESC
             LIMIT {$limit}"
        );
        $params = ['organization_id' => $organizationId];
        if ($status !== null) {
            $params['status'] = $status;
        }
        $statement->execute($params);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Expense
    {
        return new Expense(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            categoryUid: $row['category_uid'],
            supplierUid: $row['supplier_uid'],
            description: $row['description'],
            amount: Money::ofMinor((int) $row['amount_minor'], $row['currency_code']),
            expenseDate: new \DateTimeImmutable($row['expense_date']),
            receiptFileUid: $row['receipt_file_uid'],
            status: $row['status'],
            submittedBy: $row['submitted_by'] !== null ? (int) $row['submitted_by'] : null,
            approvedBy: $row['approved_by'] !== null ? (int) $row['approved_by'] : null,
            submittedAt: $row['submitted_at'] !== null ? new \DateTimeImmutable($row['submitted_at']) : null,
            approvedAt: $row['approved_at'] !== null ? new \DateTimeImmutable($row['approved_at']) : null,
            rejectedAt: $row['rejected_at'] !== null ? new \DateTimeImmutable($row['rejected_at']) : null,
            reimbursedAt: $row['reimbursed_at'] !== null ? new \DateTimeImmutable($row['reimbursed_at']) : null,
            rejectionReason: $row['rejection_reason'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
