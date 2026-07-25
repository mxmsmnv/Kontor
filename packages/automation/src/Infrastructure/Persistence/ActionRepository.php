<?php

declare(strict_types=1);

namespace Kontor\Automation\Infrastructure\Persistence;

use Kontor\Automation\Domain\RuleAction;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

final class ActionRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(RuleAction $action): void
    {
        $organizationId = $this->organizations->internalIdOf($action->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_automation_actions (uid, organization_id, rule_uid, action_key, params_json, sort_order, created_at)
             VALUES (:uid, :organization_id, :rule_uid, :action_key, :params_json, :sort_order, :created_at)'
        );

        $statement->execute([
            'uid' => $action->uid->toString(),
            'organization_id' => $organizationId,
            'rule_uid' => $action->ruleUid,
            'action_key' => $action->actionKey,
            'params_json' => $action->params !== [] ? json_encode($action->params, JSON_THROW_ON_ERROR) : null,
            'sort_order' => $action->sortOrder,
            'created_at' => $action->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @return RuleAction[]
     */
    public function forRule(string $ruleUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_automation_actions WHERE rule_uid = :rule_uid ORDER BY sort_order ASC, id ASC');
        $statement->execute(['rule_uid' => $ruleUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): RuleAction
    {
        return new RuleAction(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            ruleUid: $row['rule_uid'],
            actionKey: $row['action_key'],
            params: $row['params_json'] !== null ? json_decode($row['params_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            sortOrder: (int) $row['sort_order'],
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
