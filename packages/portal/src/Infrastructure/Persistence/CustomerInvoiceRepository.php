<?php

declare(strict_types=1);

namespace Kontor\Portal\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Domain\Invoice;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;

/**
 * The "invoices" milestone — the same read-only, customer-scoped
 * approach `CustomerQuotationRepository` uses for `kontor/sales`, applied
 * to `kontor/invoices`'s own `kontor_invoices` table.
 */
final class CustomerInvoiceRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    /**
     * @return Invoice[] newest first
     */
    public function forContact(string $organizationUid, string $contactUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_invoices
             WHERE organization_id = :organization_id AND customer_type = 'contact' AND customer_uid = :contact_uid
                AND archived_at IS NULL
             ORDER BY created_at DESC"
        );
        $statement->execute(['organization_id' => $organizationId, 'contact_uid' => $contactUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function findOwned(string $organizationUid, string $contactUid, string $invoiceUid): ?Invoice
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_invoices
             WHERE organization_id = :organization_id AND uid = :uid
                AND customer_type = 'contact' AND customer_uid = :contact_uid"
        );
        $statement->execute(['organization_id' => $organizationId, 'uid' => $invoiceUid, 'contact_uid' => $contactUid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    private function hydrate(array $row): Invoice
    {
        return new Invoice(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            number: $row['number'],
            kind: $row['kind'],
            creditedInvoiceUid: $row['credited_invoice_uid'],
            customerType: $row['customer_type'],
            customerUid: $row['customer_uid'],
            contactUid: $row['contact_uid'],
            orderUid: $row['order_uid'],
            issueDate: $row['issue_date'] !== null ? new \DateTimeImmutable($row['issue_date']) : null,
            dueDate: $row['due_date'] !== null ? new \DateTimeImmutable($row['due_date']) : null,
            documentLanguage: $row['document_language'],
            currencyCode: $row['currency_code'],
            subtotal: Money::ofMinor((int) $row['subtotal_minor'], $row['currency_code']),
            discount: Money::ofMinor((int) $row['discount_minor'], $row['currency_code']),
            tax: Money::ofMinor((int) $row['tax_minor'], $row['currency_code']),
            total: Money::ofMinor((int) $row['total_minor'], $row['currency_code']),
            paid: Money::ofMinor((int) $row['paid_minor'], $row['currency_code']),
            due: Money::ofMinor((int) $row['due_minor'], $row['currency_code']),
            status: $row['status'],
            issuedAt: $row['issued_at'] !== null ? new \DateTimeImmutable($row['issued_at']) : null,
            sentAt: $row['sent_at'] !== null ? new \DateTimeImmutable($row['sent_at']) : null,
            paidAt: $row['paid_at'] !== null ? new \DateTimeImmutable($row['paid_at']) : null,
            cancelledAt: $row['cancelled_at'] !== null ? new \DateTimeImmutable($row['cancelled_at']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
