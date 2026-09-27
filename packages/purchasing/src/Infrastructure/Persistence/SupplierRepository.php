<?php

declare(strict_types=1);

namespace Kontor\Purchasing\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Purchasing\Domain\Supplier;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class SupplierRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Supplier
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_purchasing_suppliers WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Supplier
    {
        return $this->find($id) ?? throw new RuntimeException("Supplier \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Supplier) {
            throw new InvalidArgumentException('SupplierRepository::save() expects a Supplier.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_purchasing_suppliers
                (uid, organization_id, code, legal_name, email, phone, currency_code, payment_terms_days, status,
                 created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :code, :legal_name, :email, :phone, :currency_code, :payment_terms_days, :status,
                 :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                legal_name = VALUES(legal_name), email = VALUES(email), phone = VALUES(phone),
                currency_code = VALUES(currency_code), payment_terms_days = VALUES(payment_terms_days),
                status = VALUES(status), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'code' => $entity->code,
            'legal_name' => $entity->legalName,
            'email' => $entity->email,
            'phone' => $entity->phone,
            'currency_code' => $entity->currencyCode,
            'payment_terms_days' => $entity->paymentTermsDays,
            'status' => $entity->status,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_purchasing_suppliers SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_purchasing_suppliers SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return Supplier[]
     */
    public function forOrganization(string $organizationUid, bool $archived = false): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_purchasing_suppliers
             WHERE organization_id = :organization_id
               AND ' . ($archived ? 'archived_at IS NOT NULL' : 'archived_at IS NULL') . '
             ORDER BY legal_name ASC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Supplier
    {
        return new Supplier(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            code: $row['code'],
            legalName: $row['legal_name'],
            email: $row['email'],
            phone: $row['phone'],
            currencyCode: $row['currency_code'],
            paymentTermsDays: (int) $row['payment_terms_days'],
            status: $row['status'],
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
