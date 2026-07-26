<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class PurchaseOrderRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?PurchaseOrder
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_purchasing_orders WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): PurchaseOrder
    {
        return $this->find($id) ?? throw new RuntimeException("Purchase order \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof PurchaseOrder) {
            throw new InvalidArgumentException('PurchaseOrderRepository::save() expects a PurchaseOrder.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_purchasing_orders
                (uid, organization_id, number, supplier_uid, warehouse_uid, issue_date, expected_date, currency_code,
                 subtotal_minor, discount_minor, tax_minor, total_minor, status, issued_at, received_at, cancelled_at,
                 created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :number, :supplier_uid, :warehouse_uid, :issue_date, :expected_date, :currency_code,
                 :subtotal_minor, :discount_minor, :tax_minor, :total_minor, :status, :issued_at, :received_at, :cancelled_at,
                 :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                number = VALUES(number), supplier_uid = VALUES(supplier_uid), warehouse_uid = VALUES(warehouse_uid),
                issue_date = VALUES(issue_date), expected_date = VALUES(expected_date),
                subtotal_minor = VALUES(subtotal_minor), discount_minor = VALUES(discount_minor),
                tax_minor = VALUES(tax_minor), total_minor = VALUES(total_minor), status = VALUES(status),
                issued_at = VALUES(issued_at), received_at = VALUES(received_at), cancelled_at = VALUES(cancelled_at),
                updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'number' => $entity->number,
            'supplier_uid' => $entity->supplierUid,
            'warehouse_uid' => $entity->warehouseUid,
            'issue_date' => $entity->issueDate?->format('Y-m-d'),
            'expected_date' => $entity->expectedDate?->format('Y-m-d'),
            'currency_code' => $entity->currencyCode,
            'subtotal_minor' => $entity->subtotal->amountMinor(),
            'discount_minor' => $entity->discount->amountMinor(),
            'tax_minor' => $entity->tax->amountMinor(),
            'total_minor' => $entity->total->amountMinor(),
            'status' => $entity->status,
            'issued_at' => $entity->issuedAt?->format('Y-m-d H:i:s.u'),
            'received_at' => $entity->receivedAt?->format('Y-m-d H:i:s.u'),
            'cancelled_at' => $entity->cancelledAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_purchasing_orders SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_purchasing_orders SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return PurchaseOrder[]
     */
    public function forOrganization(string $organizationUid, int $limit = 100): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $limit = max(1, min(500, $limit));
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_purchasing_orders
             WHERE organization_id = :organization_id AND archived_at IS NULL
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): PurchaseOrder
    {
        return new PurchaseOrder(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            number: $row['number'],
            supplierUid: $row['supplier_uid'],
            warehouseUid: $row['warehouse_uid'],
            issueDate: $row['issue_date'] !== null ? new \DateTimeImmutable($row['issue_date']) : null,
            expectedDate: $row['expected_date'] !== null ? new \DateTimeImmutable($row['expected_date']) : null,
            currencyCode: $row['currency_code'],
            subtotal: Money::ofMinor((int) $row['subtotal_minor'], $row['currency_code']),
            discount: Money::ofMinor((int) $row['discount_minor'], $row['currency_code']),
            tax: Money::ofMinor((int) $row['tax_minor'], $row['currency_code']),
            total: Money::ofMinor((int) $row['total_minor'], $row['currency_code']),
            status: $row['status'],
            issuedAt: $row['issued_at'] !== null ? new \DateTimeImmutable($row['issued_at']) : null,
            receivedAt: $row['received_at'] !== null ? new \DateTimeImmutable($row['received_at']) : null,
            cancelledAt: $row['cancelled_at'] !== null ? new \DateTimeImmutable($row['cancelled_at']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
