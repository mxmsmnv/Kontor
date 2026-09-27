<?php

declare(strict_types=1);

namespace Kontor\Automation\Infrastructure\Persistence;

use Kontor\Automation\Domain\RuleCondition;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

final class ConditionRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(RuleCondition $condition): void
    {
        $organizationId = $this->organizations->internalIdOf($condition->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_automation_conditions (uid, organization_id, rule_uid, field, operator, value, sort_order, created_at)
             VALUES (:uid, :organization_id, :rule_uid, :field, :operator, :value, :sort_order, :created_at)'
        );

        $statement->execute([
            'uid' => $condition->uid->toString(),
            'organization_id' => $organizationId,
            'rule_uid' => $condition->ruleUid,
            'field' => $condition->field,
            'operator' => $condition->operator,
            'value' => $condition->value,
            'sort_order' => $condition->sortOrder,
            'created_at' => $condition->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @return RuleCondition[]
     */
    public function forRule(string $ruleUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_automation_conditions WHERE rule_uid = :rule_uid ORDER BY sort_order ASC, id ASC');
        $statement->execute(['rule_uid' => $ruleUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): RuleCondition
    {
        return new RuleCondition(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            ruleUid: $row['rule_uid'],
            field: $row['field'],
            operator: $row['operator'],
            value: $row['value'],
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
