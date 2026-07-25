<?php

declare(strict_types=1);

namespace Kontor\Invoices\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Domain\Invoice;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

/**
 * kontor.md#15.3
 */
final class InvoiceRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Invoice
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_invoices WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Invoice
    {
        return $this->find($id) ?? throw new RuntimeException("Invoice \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Invoice) {
            throw new InvalidArgumentException('InvoiceRepository::save() expects an Invoice.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_invoices
                (uid, organization_id, number, kind, credited_invoice_uid, customer_type, customer_uid, contact_uid,
                 order_uid, issue_date, due_date, document_language, currency_code, subtotal_minor, discount_minor,
                 tax_minor, total_minor, paid_minor, due_minor, status, issued_at, sent_at, paid_at, cancelled_at,
                 created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :number, :kind, :credited_invoice_uid, :customer_type, :customer_uid, :contact_uid,
                 :order_uid, :issue_date, :due_date, :document_language, :currency_code, :subtotal_minor, :discount_minor,
                 :tax_minor, :total_minor, :paid_minor, :due_minor, :status, :issued_at, :sent_at, :paid_at, :cancelled_at,
                 :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                number = VALUES(number), customer_type = VALUES(customer_type), customer_uid = VALUES(customer_uid),
                contact_uid = VALUES(contact_uid), order_uid = VALUES(order_uid), issue_date = VALUES(issue_date),
                due_date = VALUES(due_date), document_language = VALUES(document_language),
                currency_code = VALUES(currency_code), subtotal_minor = VALUES(subtotal_minor),
                discount_minor = VALUES(discount_minor), tax_minor = VALUES(tax_minor), total_minor = VALUES(total_minor),
                paid_minor = VALUES(paid_minor), due_minor = VALUES(due_minor), status = VALUES(status),
                issued_at = VALUES(issued_at), sent_at = VALUES(sent_at), paid_at = VALUES(paid_at),
                cancelled_at = VALUES(cancelled_at), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'number' => $entity->number,
            'kind' => $entity->kind,
            'credited_invoice_uid' => $entity->creditedInvoiceUid,
            'customer_type' => $entity->customerType,
            'customer_uid' => $entity->customerUid,
            'contact_uid' => $entity->contactUid,
            'order_uid' => $entity->orderUid,
            'issue_date' => $entity->issueDate?->format('Y-m-d'),
            'due_date' => $entity->dueDate?->format('Y-m-d'),
            'document_language' => $entity->documentLanguage,
            'currency_code' => $entity->currencyCode,
            'subtotal_minor' => $entity->subtotal->amountMinor(),
            'discount_minor' => $entity->discount->amountMinor(),
            'tax_minor' => $entity->tax->amountMinor(),
            'total_minor' => $entity->total->amountMinor(),
            'paid_minor' => $entity->paid->amountMinor(),
            'due_minor' => $entity->due->amountMinor(),
            'status' => $entity->status,
            'issued_at' => $entity->issuedAt?->format('Y-m-d H:i:s.u'),
            'sent_at' => $entity->sentAt?->format('Y-m-d H:i:s.u'),
            'paid_at' => $entity->paidAt?->format('Y-m-d H:i:s.u'),
            'cancelled_at' => $entity->cancelledAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_invoices SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_invoices SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * Candidates for the "overdue state" milestone's sweep — sent
     * invoices whose due date has passed (kontor.md diagram 17.2: only
     * Sent transitions to Overdue).
     *
     * @return Invoice[]
     */
    public function findSentPastDue(string $organizationUid, \DateTimeImmutable $asOf): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_invoices
             WHERE organization_id = :organization_id AND status = 'sent' AND due_date < :as_of
                AND archived_at IS NULL"
        );
        $statement->execute(['organization_id' => $organizationId, 'as_of' => $asOf->format('Y-m-d')]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
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

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
