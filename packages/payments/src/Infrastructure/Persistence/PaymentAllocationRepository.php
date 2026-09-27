<?php

declare(strict_types=1);

namespace Kontor\Payments\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Payments\Domain\PaymentAllocation;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * kontor.md#15.6. No RepositoryInterface here — like kontor_document_lines'
 * DocumentLineRepository, this table's shape (no single-row "archive",
 * queried by payment/document rather than looked up one at a time) doesn't
 * fit that generic contract.
 */
final class PaymentAllocationRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(PaymentAllocation $allocation): void
    {
        $organizationId = $this->organizations->internalIdOf($allocation->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_payment_allocations
                (uid, organization_id, payment_uid, document_type, document_uid, amount_minor, currency_code,
                 allocated_at, reversed_at, metadata_json)
             VALUES
                (:uid, :organization_id, :payment_uid, :document_type, :document_uid, :amount_minor, :currency_code,
                 :allocated_at, :reversed_at, :metadata_json)
             ON DUPLICATE KEY UPDATE
                reversed_at = VALUES(reversed_at), metadata_json = VALUES(metadata_json)'
        );

        $statement->execute([
            'uid' => $allocation->uid->toString(),
            'organization_id' => $organizationId,
            'payment_uid' => $allocation->paymentUid,
            'document_type' => $allocation->documentType,
            'document_uid' => $allocation->documentUid,
            'amount_minor' => $allocation->amount->amountMinor(),
            'currency_code' => $allocation->amount->currencyCode(),
            'allocated_at' => $allocation->allocatedAt->format('Y-m-d H:i:s.u'),
            'reversed_at' => $allocation->reversedAt?->format('Y-m-d H:i:s.u'),
            'metadata_json' => $allocation->metadata !== [] ? json_encode($allocation->metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function find(string $uid): ?PaymentAllocation
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_payment_allocations WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): PaymentAllocation
    {
        return $this->find($uid) ?? throw new RuntimeException("Payment allocation \"{$uid}\" was not found.");
    }

    /**
     * @return PaymentAllocation[]
     */
    public function forPayment(string $paymentUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_payment_allocations WHERE payment_uid = :payment_uid ORDER BY allocated_at ASC');
        $statement->execute(['payment_uid' => $paymentUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return PaymentAllocation[]
     */
    public function forDocument(string $documentType, string $documentUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_payment_allocations WHERE document_type = :document_type AND document_uid = :document_uid ORDER BY allocated_at ASC'
        );
        $statement->execute(['document_type' => $documentType, 'document_uid' => $documentUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Sum of non-reversed allocations for a payment — its remaining
     * unallocated balance is amount - this.
     */
    public function totalAllocatedForPayment(string $paymentUid, string $currencyCode): Money
    {
        return $this->sumAmounts(
            array_filter($this->forPayment($paymentUid), static fn (PaymentAllocation $a): bool => !$a->isReversed()),
            $currencyCode,
        );
    }

    /**
     * Sum of non-reversed allocations for a document — used to recompute
     * an invoice's paid amount from scratch rather than incrementally, so
     * it can never drift (kontor.md's "reversals" milestone).
     */
    public function totalAllocatedForDocument(string $documentType, string $documentUid, string $currencyCode): Money
    {
        return $this->sumAmounts(
            array_filter($this->forDocument($documentType, $documentUid), static fn (PaymentAllocation $a): bool => !$a->isReversed()),
            $currencyCode,
        );
    }

    /**
     * @param PaymentAllocation[] $allocations
     */
    private function sumAmounts(array $allocations, string $currencyCode): Money
    {
        $total = Money::zero($currencyCode);

        foreach ($allocations as $allocation) {
            $total = $total->add($allocation->amount);
        }

        return $total;
    }

    private function hydrate(array $row): PaymentAllocation
    {
        return new PaymentAllocation(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            paymentUid: $row['payment_uid'],
            documentType: $row['document_type'],
            documentUid: $row['document_uid'],
            amount: Money::ofMinor((int) $row['amount_minor'], $row['currency_code']),
            allocatedAt: new \DateTimeImmutable($row['allocated_at']),
            reversedAt: $row['reversed_at'] !== null ? new \DateTimeImmutable($row['reversed_at']) : null,
            metadata: $row['metadata_json'] !== null ? json_decode($row['metadata_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
