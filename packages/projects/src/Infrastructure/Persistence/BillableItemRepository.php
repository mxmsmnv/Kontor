<?php

declare(strict_types=1);

namespace Kontor\Projects\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Domain\BillableItem;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class BillableItemRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(BillableItem $item): void
    {
        $organizationId = $this->organizations->internalIdOf($item->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_project_billable_items
                (uid, organization_id, project_uid, milestone_uid, description, quantity_decimal, unit_price_minor,
                 currency_code, invoice_line_uid, created_at)
             VALUES
                (:uid, :organization_id, :project_uid, :milestone_uid, :description, :quantity_decimal, :unit_price_minor,
                 :currency_code, :invoice_line_uid, :created_at)
             ON DUPLICATE KEY UPDATE
                description = VALUES(description), quantity_decimal = VALUES(quantity_decimal),
                unit_price_minor = VALUES(unit_price_minor), invoice_line_uid = VALUES(invoice_line_uid)'
        );

        $statement->execute([
            'uid' => $item->uid->toString(),
            'organization_id' => $organizationId,
            'project_uid' => $item->projectUid,
            'milestone_uid' => $item->milestoneUid,
            'description' => $item->description,
            'quantity_decimal' => $item->quantity,
            'unit_price_minor' => $item->unitPrice->amountMinor(),
            'currency_code' => $item->unitPrice->currencyCode(),
            'invoice_line_uid' => $item->invoiceLineUid,
            'created_at' => $item->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function find(string $uid): ?BillableItem
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_project_billable_items WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): BillableItem
    {
        return $this->find($uid) ?? throw new RuntimeException("Billable item \"{$uid}\" was not found.");
    }

    /**
     * @return BillableItem[]
     */
    public function forProject(string $projectUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_project_billable_items WHERE project_uid = :project_uid ORDER BY created_at ASC');
        $statement->execute(['project_uid' => $projectUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return BillableItem[]
     */
    public function uninvoicedFor(string $projectUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_project_billable_items WHERE project_uid = :project_uid AND invoice_line_uid IS NULL ORDER BY created_at ASC'
        );
        $statement->execute(['project_uid' => $projectUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): BillableItem
    {
        return new BillableItem(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            projectUid: $row['project_uid'],
            milestoneUid: $row['milestone_uid'],
            description: $row['description'],
            quantity: (float) $row['quantity_decimal'],
            unitPrice: Money::ofMinor((int) $row['unit_price_minor'], $row['currency_code']),
            invoiceLineUid: $row['invoice_line_uid'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
