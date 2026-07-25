<?php

declare(strict_types=1);

namespace Kontor\API\Infrastructure\Persistence;

use Kontor\API\Domain\WebhookDelivery;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

/**
 * Not a `RepositoryInterface` implementation — like
 * `Kontor\Automation\Infrastructure\Persistence\ExecutionLogRepository`,
 * a delivery log has no `archive()`/`restore()` concept of its own.
 */
final class WebhookDeliveryRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $uid): ?WebhookDelivery
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_webhook_deliveries WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): WebhookDelivery
    {
        return $this->find($uid) ?? throw new RuntimeException("Webhook delivery \"{$uid}\" was not found.");
    }

    public function save(WebhookDelivery $delivery): void
    {
        $organizationId = $this->organizations->internalIdOf($delivery->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_webhook_deliveries
                (uid, organization_id, subscription_uid, event_uid, event_name, payload_json, status,
                 attempt_count, response_code, last_error, next_attempt_at, delivered_at, created_at, updated_at)
             VALUES
                (:uid, :organization_id, :subscription_uid, :event_uid, :event_name, :payload_json, :status,
                 :attempt_count, :response_code, :last_error, :next_attempt_at, :delivered_at, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status), attempt_count = VALUES(attempt_count),
                response_code = VALUES(response_code), last_error = VALUES(last_error),
                next_attempt_at = VALUES(next_attempt_at), delivered_at = VALUES(delivered_at),
                updated_at = VALUES(updated_at)'
        );

        $statement->execute([
            'uid' => $delivery->uid->toString(),
            'organization_id' => $organizationId,
            'subscription_uid' => $delivery->subscriptionUid,
            'event_uid' => $delivery->eventUid,
            'event_name' => $delivery->eventName,
            'payload_json' => json_encode($delivery->payload, JSON_THROW_ON_ERROR),
            'status' => $delivery->status,
            'attempt_count' => $delivery->attemptCount,
            'response_code' => $delivery->responseCode,
            'last_error' => $delivery->lastError,
            'next_attempt_at' => $delivery->nextAttemptAt?->format('Y-m-d H:i:s.u'),
            'delivered_at' => $delivery->deliveredAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $delivery->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $delivery->updatedAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * Deliveries a retry worker should pick up next.
     *
     * @return WebhookDelivery[]
     */
    public function due(int $limit = 50): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_webhook_deliveries
             WHERE status = 'pending' AND (next_attempt_at IS NULL OR next_attempt_at <= :now)
             ORDER BY created_at ASC
             LIMIT :limit"
        );
        $statement->bindValue('now', (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'));
        $statement->bindValue('limit', $limit, \PDO::PARAM_INT);
        $statement->execute();

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * @return WebhookDelivery[]
     */
    public function forSubscription(string $subscriptionUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_webhook_deliveries WHERE subscription_uid = :subscription_uid ORDER BY created_at DESC');
        $statement->execute(['subscription_uid' => $subscriptionUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): WebhookDelivery
    {
        return new WebhookDelivery(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            subscriptionUid: $row['subscription_uid'],
            eventUid: $row['event_uid'],
            eventName: $row['event_name'],
            payload: json_decode($row['payload_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            status: $row['status'],
            attemptCount: (int) $row['attempt_count'],
            responseCode: $row['response_code'] !== null ? (int) $row['response_code'] : null,
            lastError: $row['last_error'],
            nextAttemptAt: $row['next_attempt_at'] !== null ? new \DateTimeImmutable($row['next_attempt_at']) : null,
            deliveredAt: $row['delivered_at'] !== null ? new \DateTimeImmutable($row['delivered_at']) : null,
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
