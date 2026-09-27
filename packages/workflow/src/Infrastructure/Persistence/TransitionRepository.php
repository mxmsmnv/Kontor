<?php

declare(strict_types=1);

namespace Kontor\Workflow\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Workflow\Domain\WorkflowTransition;

final class TransitionRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(WorkflowTransition $transition): void
    {
        $organizationId = $this->organizations->internalIdOf($transition->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_workflow_transitions
                (uid, organization_id, definition_uid, action_key, from_state, to_state, required_permission,
                 requires_approval, created_at)
             VALUES
                (:uid, :organization_id, :definition_uid, :action_key, :from_state, :to_state, :required_permission,
                 :requires_approval, :created_at)'
        );

        $statement->execute([
            'uid' => $transition->uid->toString(),
            'organization_id' => $organizationId,
            'definition_uid' => $transition->definitionUid,
            'action_key' => $transition->actionKey,
            'from_state' => $transition->fromState,
            'to_state' => $transition->toState,
            'required_permission' => $transition->requiredPermission,
            'requires_approval' => $transition->requiresApproval ? 1 : 0,
            'created_at' => $transition->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * The engine's core lookup: given where an entity is and what action
     * is being requested, is there exactly one place it goes? No row
     * found means the action is invalid from this state — including a
     * state with no outgoing transitions at all ("immutable").
     */
    public function findByFromStateAndAction(string $definitionUid, string $fromState, string $actionKey): ?WorkflowTransition
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_workflow_transitions WHERE definition_uid = :definition_uid AND from_state = :from_state AND action_key = :action_key'
        );
        $statement->execute(['definition_uid' => $definitionUid, 'from_state' => $fromState, 'action_key' => $actionKey]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * Every action available from a given state — the "visual editor"
     * milestone's backend: a UI would call this to know which buttons to
     * show.
     *
     * @return WorkflowTransition[]
     */
    public function fromState(string $definitionUid, string $fromState): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_workflow_transitions WHERE definition_uid = :definition_uid AND from_state = :from_state ORDER BY id ASC');
        $statement->execute(['definition_uid' => $definitionUid, 'from_state' => $fromState]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return WorkflowTransition[]
     */
    public function forDefinition(string $definitionUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_workflow_transitions WHERE definition_uid = :definition_uid ORDER BY id ASC');
        $statement->execute(['definition_uid' => $definitionUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): WorkflowTransition
    {
        return new WorkflowTransition(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            definitionUid: $row['definition_uid'],
            actionKey: $row['action_key'],
            fromState: $row['from_state'],
            toState: $row['to_state'],
            requiredPermission: $row['required_permission'],
            requiresApproval: (bool) $row['requires_approval'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
