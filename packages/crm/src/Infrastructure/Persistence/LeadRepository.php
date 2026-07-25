<?php

declare(strict_types=1);

namespace Kontor\CRM\Infrastructure\Persistence;

use Kontor\CRM\Domain\Lead;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

/**
 * kontor.md#13.1
 */
final class LeadRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Lead
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_crm_leads WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Lead
    {
        return $this->find($id) ?? throw new RuntimeException("Lead \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Lead) {
            throw new InvalidArgumentException('LeadRepository::save() expects a Lead.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_crm_leads
                (uid, organization_id, title, contact_uid, company_uid, source, status, priority,
                 estimated_value_minor, currency_code, assigned_user_id, next_action_at, converted_deal_uid,
                 lost_reason, description, created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :title, :contact_uid, :company_uid, :source, :status, :priority,
                 :estimated_value_minor, :currency_code, :assigned_user_id, :next_action_at, :converted_deal_uid,
                 :lost_reason, :description, :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                title = VALUES(title), contact_uid = VALUES(contact_uid), company_uid = VALUES(company_uid),
                source = VALUES(source), status = VALUES(status), priority = VALUES(priority),
                estimated_value_minor = VALUES(estimated_value_minor), currency_code = VALUES(currency_code),
                assigned_user_id = VALUES(assigned_user_id), next_action_at = VALUES(next_action_at),
                converted_deal_uid = VALUES(converted_deal_uid), lost_reason = VALUES(lost_reason),
                description = VALUES(description), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'title' => $entity->title,
            'contact_uid' => $entity->contactUid,
            'company_uid' => $entity->companyUid,
            'source' => $entity->source,
            'status' => $entity->status,
            'priority' => $entity->priority,
            'estimated_value_minor' => $entity->estimatedValue?->amountMinor(),
            'currency_code' => $entity->estimatedValue?->currencyCode(),
            'assigned_user_id' => $entity->assignedUserId,
            'next_action_at' => $entity->nextActionAt?->format('Y-m-d H:i:s.u'),
            'converted_deal_uid' => $entity->convertedDealUid,
            'lost_reason' => $entity->lostReason,
            'description' => $entity->description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_crm_leads SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_crm_leads SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return array<int, Lead>
     */
    public function forOrganization(string $organizationUid, ?string $status = null): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $sql = 'SELECT * FROM kontor_crm_leads WHERE organization_id = :organization_id';
        $params = ['organization_id' => $organizationId];

        if ($status !== null) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return array_map(fn (array $row): Lead => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Lead
    {
        return new Lead(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            title: $row['title'],
            contactUid: $row['contact_uid'],
            companyUid: $row['company_uid'],
            source: $row['source'],
            status: $row['status'],
            priority: $row['priority'],
            estimatedValue: $row['estimated_value_minor'] !== null && $row['currency_code'] !== null
                ? Money::ofMinor((int) $row['estimated_value_minor'], $row['currency_code'])
                : null,
            assignedUserId: $row['assigned_user_id'] !== null ? (int) $row['assigned_user_id'] : null,
            nextActionAt: $row['next_action_at'] !== null ? new \DateTimeImmutable($row['next_action_at']) : null,
            convertedDealUid: $row['converted_deal_uid'],
            lostReason: $row['lost_reason'],
            description: $row['description'],
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
