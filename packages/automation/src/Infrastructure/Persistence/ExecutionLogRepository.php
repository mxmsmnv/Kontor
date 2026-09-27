<?php

declare(strict_types=1);

namespace Kontor\Automation\Infrastructure\Persistence;

use Kontor\Automation\Domain\ExecutionLog;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

final class ExecutionLogRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function insert(ExecutionLog $log): void
    {
        $organizationId = $this->organizations->internalIdOf($log->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_automation_execution_logs
                (uid, organization_id, rule_uid, trigger_event, matched, dry_run, recursion_blocked,
                 actions_result_json, error, occurred_at)
             VALUES
                (:uid, :organization_id, :rule_uid, :trigger_event, :matched, :dry_run, :recursion_blocked,
                 :actions_result_json, :error, :occurred_at)'
        );

        $statement->execute([
            'uid' => $log->uid->toString(),
            'organization_id' => $organizationId,
            'rule_uid' => $log->ruleUid,
            'trigger_event' => $log->triggerEvent,
            'matched' => $log->matched ? 1 : 0,
            'dry_run' => $log->dryRun ? 1 : 0,
            'recursion_blocked' => $log->recursionBlocked ? 1 : 0,
            'actions_result_json' => $log->actionsResult !== [] ? json_encode($log->actionsResult, JSON_THROW_ON_ERROR) : null,
            'error' => $log->error,
            'occurred_at' => $log->occurredAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @return ExecutionLog[] newest first
     */
    public function forRule(string $ruleUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_automation_execution_logs WHERE rule_uid = :rule_uid ORDER BY occurred_at DESC');
        $statement->execute(['rule_uid' => $ruleUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return ExecutionLog[] newest first
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_automation_execution_logs WHERE organization_id = :organization_id ORDER BY occurred_at DESC');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): ExecutionLog
    {
        return new ExecutionLog(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            ruleUid: $row['rule_uid'],
            triggerEvent: $row['trigger_event'],
            matched: (bool) $row['matched'],
            dryRun: (bool) $row['dry_run'],
            recursionBlocked: (bool) $row['recursion_blocked'],
            actionsResult: $row['actions_result_json'] !== null ? json_decode($row['actions_result_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            error: $row['error'],
            occurredAt: new \DateTimeImmutable($row['occurred_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
