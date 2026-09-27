<?php

declare(strict_types=1);

namespace Kontor\Payments\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Payments\Domain\Payment;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

/**
 * kontor.md#15.5
 */
final class PaymentRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Payment
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_payments WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Payment
    {
        return $this->find($id) ?? throw new RuntimeException("Payment \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Payment) {
            throw new InvalidArgumentException('PaymentRepository::save() expects a Payment.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_payments
                (uid, organization_id, number, payer_type, payer_uid, payment_date, amount_minor, currency_code,
                 method, transaction_reference, status, external_id, metadata_json, created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :number, :payer_type, :payer_uid, :payment_date, :amount_minor, :currency_code,
                 :method, :transaction_reference, :status, :external_id, :metadata_json, :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                number = VALUES(number), payer_type = VALUES(payer_type), payer_uid = VALUES(payer_uid),
                payment_date = VALUES(payment_date), amount_minor = VALUES(amount_minor),
                currency_code = VALUES(currency_code), method = VALUES(method),
                transaction_reference = VALUES(transaction_reference), status = VALUES(status),
                external_id = VALUES(external_id), metadata_json = VALUES(metadata_json),
                updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'number' => $entity->number,
            'payer_type' => $entity->payerType,
            'payer_uid' => $entity->payerUid,
            'payment_date' => $entity->paymentDate?->format('Y-m-d'),
            'amount_minor' => $entity->amount->amountMinor(),
            'currency_code' => $entity->amount->currencyCode(),
            'method' => $entity->method,
            'transaction_reference' => $entity->transactionReference,
            'status' => $entity->status,
            'external_id' => $entity->externalId,
            'metadata_json' => $entity->metadata !== [] ? json_encode($entity->metadata, JSON_THROW_ON_ERROR) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_payments SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_payments SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return array<int, Payment>
     */
    public function findMatching(
        string $organizationUid,
        string $query = '',
        ?string $status = null,
        bool $archived = false,
        int $limit = 50,
        int $offset = 0,
    ): array {
        [$where, $params] = $this->matchingConditions($organizationUid, $query, $status, $archived);
        $statement = $this->pdo->prepare(
            'SELECT p.* FROM kontor_payments p
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY p.payment_date DESC, p.updated_at DESC, p.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): Payment => $this->hydrate($row),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function countMatching(
        string $organizationUid,
        string $query = '',
        ?string $status = null,
        bool $archived = false,
    ): int {
        [$where, $params] = $this->matchingConditions($organizationUid, $query, $status, $archived);
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM kontor_payments p WHERE ' . implode(' AND ', $where)
        );
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return array{0: array<int, string>, 1: array<string, int|string>}
     */
    private function matchingConditions(
        string $organizationUid,
        string $query,
        ?string $status,
        bool $archived,
    ): array {
        $where = [
            'p.organization_id = :organization_id',
            'p.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL'),
        ];
        $params = [
            'organization_id' => $this->organizations->internalIdOf($organizationUid),
        ];

        if ($status !== null) {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $query = trim($query);
        if ($query !== '') {
            $where[] = '(p.number LIKE :query OR p.transaction_reference LIKE :query OR p.payer_uid LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        return [$where, $params];
    }

    private function hydrate(array $row): Payment
    {
        return new Payment(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            number: $row['number'],
            payerType: $row['payer_type'],
            payerUid: $row['payer_uid'],
            paymentDate: $row['payment_date'] !== null ? new \DateTimeImmutable($row['payment_date']) : null,
            amount: Money::ofMinor((int) $row['amount_minor'], $row['currency_code']),
            method: $row['method'],
            transactionReference: $row['transaction_reference'],
            status: $row['status'],
            externalId: $row['external_id'],
            metadata: $row['metadata_json'] !== null ? json_decode($row['metadata_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
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
