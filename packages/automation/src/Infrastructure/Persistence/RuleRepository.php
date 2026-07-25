<?php

declare(strict_types=1);

namespace Kontor\Automation\Infrastructure\Persistence;

use Kontor\Automation\Domain\AutomationRule;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use InvalidArgumentException;
use RuntimeException;

final class RuleRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?AutomationRule
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_automation_rules WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): AutomationRule
    {
        return $this->find($id) ?? throw new RuntimeException("Automation rule \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof AutomationRule) {
            throw new InvalidArgumentException('RuleRepository::save() expects an AutomationRule.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_automation_rules (uid, organization_id, name, trigger_event, status, created_at, updated_at, created_by, version)
             VALUES (:uid, :organization_id, :name, :trigger_event, :status, :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE name = VALUES(name), status = VALUES(status), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'name' => $entity->name,
            'trigger_event' => $entity->triggerEvent,
            'status' => $entity->status,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_automation_rules SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_automation_rules SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * Active rules matching a trigger event, for a specific organization —
     * what AutomationEngine::handleEvent() evaluates on every dispatch.
     *
     * @return AutomationRule[]
     */
    public function activeForTrigger(string $organizationUid, string $triggerEvent): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_automation_rules
             WHERE organization_id = :organization_id AND trigger_event = :trigger_event AND status = 'active'
                AND archived_at IS NULL
             ORDER BY id ASC"
        );
        $statement->execute(['organization_id' => $organizationId, 'trigger_event' => $triggerEvent]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Every distinct trigger event across every active rule, in any
     * organization — what KontorAutomation::init() subscribes to on
     * Core's real EventDispatcherInterface.
     *
     * @return string[]
     */
    public function distinctActiveTriggerEvents(): array
    {
        $statement = $this->pdo->query("SELECT DISTINCT trigger_event FROM kontor_automation_rules WHERE status = 'active' AND archived_at IS NULL");

        return array_column($statement->fetchAll(\PDO::FETCH_ASSOC), 'trigger_event');
    }

    private function hydrate(array $row): AutomationRule
    {
        return new AutomationRule(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            name: $row['name'],
            triggerEvent: $row['trigger_event'],
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
