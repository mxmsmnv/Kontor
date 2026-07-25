<?php

declare(strict_types=1);

namespace Kontor\CRM\Infrastructure\Persistence;

use Kontor\CRM\Domain\Deal;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

/**
 * kontor.md#13.4
 */
final class DealRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?Deal
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_crm_deals WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): Deal
    {
        return $this->find($id) ?? throw new RuntimeException("Deal \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof Deal) {
            throw new InvalidArgumentException('DealRepository::save() expects a Deal.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);
        $now = $this->now();

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_crm_deals
                (uid, organization_id, pipeline_uid, stage_uid, title, contact_uid, company_uid, assigned_user_id,
                 value_minor, currency_code, probability, expected_close_date, source, status, won_at, lost_at,
                 lost_reason, description, created_at, updated_at, version)
             VALUES
                (:uid, :organization_id, :pipeline_uid, :stage_uid, :title, :contact_uid, :company_uid, :assigned_user_id,
                 :value_minor, :currency_code, :probability, :expected_close_date, :source, :status, :won_at, :lost_at,
                 :lost_reason, :description, :created_at, :updated_at, 1)
             ON DUPLICATE KEY UPDATE
                pipeline_uid = VALUES(pipeline_uid), stage_uid = VALUES(stage_uid), title = VALUES(title),
                contact_uid = VALUES(contact_uid), company_uid = VALUES(company_uid),
                assigned_user_id = VALUES(assigned_user_id), value_minor = VALUES(value_minor),
                currency_code = VALUES(currency_code), probability = VALUES(probability),
                expected_close_date = VALUES(expected_close_date), source = VALUES(source), status = VALUES(status),
                won_at = VALUES(won_at), lost_at = VALUES(lost_at), lost_reason = VALUES(lost_reason),
                description = VALUES(description), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'pipeline_uid' => $entity->pipelineUid,
            'stage_uid' => $entity->stageUid,
            'title' => $entity->title,
            'contact_uid' => $entity->contactUid,
            'company_uid' => $entity->companyUid,
            'assigned_user_id' => $entity->assignedUserId,
            'value_minor' => $entity->value?->amountMinor(),
            'currency_code' => $entity->value?->currencyCode(),
            'probability' => $entity->probability,
            'expected_close_date' => $entity->expectedCloseDate?->format('Y-m-d'),
            'source' => $entity->source,
            'status' => $entity->status,
            'won_at' => $entity->wonAt?->format('Y-m-d H:i:s.u'),
            'lost_at' => $entity->lostAt?->format('Y-m-d H:i:s.u'),
            'lost_reason' => $entity->lostReason,
            'description' => $entity->description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_crm_deals SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => $this->now(), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_crm_deals SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return array<int, Deal>
     */
    public function forStage(string $stageUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_crm_deals WHERE stage_uid = :stage_uid ORDER BY id ASC');
        $statement->execute(['stage_uid' => $stageUid]);

        return array_map(fn (array $row): Deal => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Deal
    {
        return new Deal(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            pipelineUid: $row['pipeline_uid'],
            stageUid: $row['stage_uid'],
            title: $row['title'],
            contactUid: $row['contact_uid'],
            companyUid: $row['company_uid'],
            assignedUserId: $row['assigned_user_id'] !== null ? (int) $row['assigned_user_id'] : null,
            value: $row['value_minor'] !== null && $row['currency_code'] !== null
                ? Money::ofMinor((int) $row['value_minor'], $row['currency_code'])
                : null,
            probability: $row['probability'] !== null ? (int) $row['probability'] : null,
            expectedCloseDate: $row['expected_close_date'] !== null ? new \DateTimeImmutable($row['expected_close_date']) : null,
            source: $row['source'],
            status: $row['status'],
            wonAt: $row['won_at'] !== null ? new \DateTimeImmutable($row['won_at']) : null,
            lostAt: $row['lost_at'] !== null ? new \DateTimeImmutable($row['lost_at']) : null,
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
