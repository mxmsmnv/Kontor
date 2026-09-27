<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\SDK\ValueObjects\Uid;

/**
 * Writes to kontor_audit_events (kontor.md#11.4). Every mutating admin
 * action, AJAX endpoint, API call and queue job must call this — codex
 * rule #7 forbids skipping permission checks and audit is how those checks
 * become reviewable after the fact.
 */
final class AuditLogger
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /**
     * @param int $organizationId the internal kontor_organizations.id (BIGINT FK), not its public uid (kontor.md#10.4)
     * @param array<string, mixed>|null $previous
     * @param array<string, mixed>|null $current
     * @param array<string, mixed> $metadata
     */
    public function record(
        int $organizationId,
        string $component,
        string $entityType,
        string $entityUid,
        string $action,
        string $actorType,
        ?string $actorUid,
        ?array $previous = null,
        ?array $current = null,
        array $metadata = [],
        ?string $requestId = null,
        ?string $correlationId = null,
        ?string $ipAddress = null,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_audit_events
                (uid, organization_id, component, entity_type, entity_uid, action, actor_type, actor_uid,
                 occurred_at, request_id, correlation_id, ip_address, previous_json, current_json, metadata_json)
             VALUES
                (:uid, :organization_id, :component, :entity_type, :entity_uid, :action, :actor_type, :actor_uid,
                 :occurred_at, :request_id, :correlation_id, :ip_address, :previous_json, :current_json, :metadata_json)'
        );

        $statement->execute([
            'uid' => Uid::generate()->toString(),
            'organization_id' => $organizationId,
            'component' => $component,
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
            'action' => $action,
            'actor_type' => $actorType,
            'actor_uid' => $actorUid,
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'ip_address' => $ipAddress,
            'previous_json' => $previous !== null ? json_encode($previous, JSON_THROW_ON_ERROR) : null,
            'current_json' => $current !== null ? json_encode($current, JSON_THROW_ON_ERROR) : null,
            'metadata_json' => $metadata !== [] ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
        ]);
    }
}
