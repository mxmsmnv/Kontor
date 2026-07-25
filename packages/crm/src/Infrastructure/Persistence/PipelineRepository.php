<?php

declare(strict_types=1);

namespace Kontor\CRM\Infrastructure\Persistence;

use Kontor\CRM\Domain\Pipeline;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * kontor.md#13.2
 */
final class PipelineRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(Pipeline $pipeline): void
    {
        $organizationId = $this->organizations->internalIdOf($pipeline->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_crm_pipelines
                (uid, organization_id, name, entity_type, is_default, status, settings_json)
             VALUES
                (:uid, :organization_id, :name, :entity_type, :is_default, :status, :settings_json)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), entity_type = VALUES(entity_type), is_default = VALUES(is_default),
                status = VALUES(status), settings_json = VALUES(settings_json)'
        );

        $statement->execute([
            'uid' => $pipeline->uid->toString(),
            'organization_id' => $organizationId,
            'name' => $pipeline->name,
            'entity_type' => $pipeline->entityType,
            'is_default' => $pipeline->isDefault ? 1 : 0,
            'status' => $pipeline->status,
            'settings_json' => $pipeline->settings !== [] ? json_encode($pipeline->settings, JSON_THROW_ON_ERROR) : null,
        ]);
    }

    public function find(string $uid): ?Pipeline
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_crm_pipelines WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): Pipeline
    {
        return $this->find($uid) ?? throw new RuntimeException("Pipeline \"{$uid}\" was not found.");
    }

    public function defaultForEntityType(string $organizationUid, string $entityType): ?Pipeline
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_crm_pipelines
             WHERE organization_id = :organization_id AND entity_type = :entity_type AND is_default = 1
             LIMIT 1'
        );
        $statement->execute(['organization_id' => $organizationId, 'entity_type' => $entityType]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return array<int, Pipeline>
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_crm_pipelines WHERE organization_id = :organization_id');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map(fn (array $row): Pipeline => $this->hydrate($row), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): Pipeline
    {
        return new Pipeline(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            name: $row['name'],
            entityType: $row['entity_type'],
            isDefault: (bool) $row['is_default'],
            status: $row['status'],
            settings: $row['settings_json'] !== null ? json_decode($row['settings_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
