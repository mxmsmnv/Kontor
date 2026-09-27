<?php

declare(strict_types=1);

namespace Kontor\Inventory\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

/**
 * The "barcode support" milestone's own gap-fill table (see the
 * migration's doc comment) — a scanned barcode resolves to an item_uid.
 */
final class BarcodeRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function register(string $organizationUid, string $barcode, string $itemUid): void
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_inventory_barcodes (organization_id, barcode, item_uid, created_at)
             VALUES (:organization_id, :barcode, :item_uid, :created_at)
             ON DUPLICATE KEY UPDATE item_uid = VALUES(item_uid)'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'barcode' => $barcode,
            'item_uid' => $itemUid,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function resolve(string $organizationUid, string $barcode): ?string
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT item_uid FROM kontor_inventory_barcodes WHERE organization_id = :organization_id AND barcode = :barcode'
        );
        $statement->execute(['organization_id' => $organizationId, 'barcode' => $barcode]);

        $itemUid = $statement->fetchColumn();

        return $itemUid === false ? null : $itemUid;
    }
}
