<?php

declare(strict_types=1);

namespace Kontor\Sales\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Sales\Domain\Quotation;
use InvalidArgumentException;
use RuntimeException;

/**
 * kontor.md#15.1
 */
final class QuotationRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Quotation
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_sales_quotations WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Quotation
    {
        return $this->find($id) ?? throw new RuntimeException("Quotation \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Quotation) {
            throw new InvalidArgumentException('QuotationRepository::save() expects a Quotation.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_sales_quotations
                (uid, organization_id, number, customer_type, customer_uid, contact_uid, deal_uid, issue_date,
                 valid_until, document_language, currency_code, subtotal_minor, discount_minor, tax_minor,
                 total_minor, status, template_uid, snapshot_json, issued_at, accepted_at, rejected_at,
                 created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :number, :customer_type, :customer_uid, :contact_uid, :deal_uid, :issue_date,
                 :valid_until, :document_language, :currency_code, :subtotal_minor, :discount_minor, :tax_minor,
                 :total_minor, :status, :template_uid, :snapshot_json, :issued_at, :accepted_at, :rejected_at,
                 :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                number = VALUES(number), customer_type = VALUES(customer_type), customer_uid = VALUES(customer_uid),
                contact_uid = VALUES(contact_uid), deal_uid = VALUES(deal_uid), issue_date = VALUES(issue_date),
                valid_until = VALUES(valid_until), document_language = VALUES(document_language),
                currency_code = VALUES(currency_code), subtotal_minor = VALUES(subtotal_minor),
                discount_minor = VALUES(discount_minor), tax_minor = VALUES(tax_minor),
                total_minor = VALUES(total_minor), status = VALUES(status), issued_at = VALUES(issued_at),
                template_uid = VALUES(template_uid), snapshot_json = VALUES(snapshot_json),
                accepted_at = VALUES(accepted_at), rejected_at = VALUES(rejected_at), updated_at = VALUES(updated_at),
                version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'number' => $entity->number,
            'customer_type' => $entity->customerType,
            'customer_uid' => $entity->customerUid,
            'contact_uid' => $entity->contactUid,
            'deal_uid' => $entity->dealUid,
            'issue_date' => $entity->issueDate?->format('Y-m-d'),
            'valid_until' => $entity->validUntil?->format('Y-m-d'),
            'document_language' => $entity->documentLanguage,
            'currency_code' => $entity->currencyCode,
            'subtotal_minor' => $entity->subtotal->amountMinor(),
            'discount_minor' => $entity->discount->amountMinor(),
            'tax_minor' => $entity->tax->amountMinor(),
            'total_minor' => $entity->total->amountMinor(),
            'status' => $entity->status,
            'template_uid' => $entity->templateUid,
            'snapshot_json' => $entity->snapshot !== []
                ? json_encode($entity->snapshot, JSON_THROW_ON_ERROR)
                : null,
            'issued_at' => $entity->issuedAt?->format('Y-m-d H:i:s.u'),
            'accepted_at' => $entity->acceptedAt?->format('Y-m-d H:i:s.u'),
            'rejected_at' => $entity->rejectedAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_sales_quotations SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_sales_quotations SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return array<int, Quotation>
     */
    public function findMatching(
        string $organizationUid,
        string $query = '',
        ?string $status = null,
        bool $archived = false,
        int $limit = 50,
        int $offset = 0,
    ): array
    {
        [$where, $params] = $this->matchingConditions($organizationUid, $query, $status, $archived);
        $statement = $this->pdo->prepare(
            'SELECT q.* FROM kontor_sales_quotations q
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY q.updated_at DESC, q.id DESC
             LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value);
        }
        $statement->bindValue(':limit', max(1, $limit), \PDO::PARAM_INT);
        $statement->bindValue(':offset', max(0, $offset), \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            fn (array $row): Quotation => $this->hydrate($row),
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    public function countMatching(
        string $organizationUid,
        string $query = '',
        ?string $status = null,
        bool $archived = false,
    ): int
    {
        [$where, $params] = $this->matchingConditions($organizationUid, $query, $status, $archived);
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM kontor_sales_quotations q WHERE ' . implode(' AND ', $where)
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
    ): array
    {
        $where = [
            'q.organization_id = :organization_id',
            'q.archived_at IS ' . ($archived ? 'NOT NULL' : 'NULL'),
        ];
        $params = [
            'organization_id' => $this->organizations->internalIdOf($organizationUid),
        ];

        if ($status !== null) {
            $where[] = 'q.status = :status';
            $params['status'] = $status;
        }

        $query = trim($query);
        if ($query !== '') {
            $where[] = '(q.number LIKE :query OR q.customer_uid LIKE :query)';
            $params['query'] = '%' . $query . '%';
        }

        return [$where, $params];
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
            templateUid: $row['template_uid'],
            snapshot: $row['snapshot_json'] !== null
                ? json_decode($row['snapshot_json'], associative: true, flags: JSON_THROW_ON_ERROR)
                : [],
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
