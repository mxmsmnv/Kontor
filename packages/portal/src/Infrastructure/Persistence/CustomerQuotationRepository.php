<?php

declare(strict_types=1);

namespace Kontor\Portal\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Sales\Domain\Quotation;

/**
 * The "quotations" milestone: a read-only, customer-scoped view over
 * `kontor/sales`'s own `kontor_sales_quotations` table. Hydrates the real
 * `Kontor\Sales\Domain\Quotation` (its constructor is public, the same
 * hydration `Kontor\Sales\Infrastructure\Persistence\QuotationRepository`
 * does internally) rather than a parallel DTO — but this class is its own
 * repository, not a modification of Sales' own (never retrofitted with a
 * "find by customer" method it doesn't otherwise need).
 */
final class CustomerQuotationRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    /**
     * @return Quotation[] newest first
     */
    public function forContact(string $organizationUid, string $contactUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_sales_quotations
             WHERE organization_id = :organization_id AND customer_type = 'contact' AND customer_uid = :contact_uid
                AND archived_at IS NULL
             ORDER BY created_at DESC"
        );
        $statement->execute(['organization_id' => $organizationId, 'contact_uid' => $contactUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Only returns the quotation if it actually belongs to this contact —
     * the ownership check every customer-facing lookup in this package
     * must make.
     */
    public function findOwned(string $organizationUid, string $contactUid, string $quotationUid): ?Quotation
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_sales_quotations
             WHERE organization_id = :organization_id AND uid = :uid
                AND customer_type = 'contact' AND customer_uid = :contact_uid"
        );
        $statement->execute(['organization_id' => $organizationId, 'uid' => $quotationUid, 'contact_uid' => $contactUid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    private function hydrate(array $row): Quotation
    {
        return new Quotation(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            number: $row['number'],
            customerType: $row['customer_type'],
            customerUid: $row['customer_uid'],
            contactUid: $row['contact_uid'],
            dealUid: $row['deal_uid'],
            issueDate: $row['issue_date'] !== null ? new \DateTimeImmutable($row['issue_date']) : null,
            validUntil: $row['valid_until'] !== null ? new \DateTimeImmutable($row['valid_until']) : null,
            documentLanguage: $row['document_language'],
            currencyCode: $row['currency_code'],
            subtotal: Money::ofMinor((int) $row['subtotal_minor'], $row['currency_code']),
            discount: Money::ofMinor((int) $row['discount_minor'], $row['currency_code']),
            tax: Money::ofMinor((int) $row['tax_minor'], $row['currency_code']),
            total: Money::ofMinor((int) $row['total_minor'], $row['currency_code']),
            status: $row['status'],
            issuedAt: $row['issued_at'] !== null ? new \DateTimeImmutable($row['issued_at']) : null,
            acceptedAt: $row['accepted_at'] !== null ? new \DateTimeImmutable($row['accepted_at']) : null,
            rejectedAt: $row['rejected_at'] !== null ? new \DateTimeImmutable($row['rejected_at']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
