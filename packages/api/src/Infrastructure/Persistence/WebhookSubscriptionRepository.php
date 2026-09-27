<?php

declare(strict_types=1);

namespace Kontor\API\Infrastructure\Persistence;

use InvalidArgumentException;
use Kontor\API\Domain\WebhookSubscription;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class WebhookSubscriptionRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?WebhookSubscription
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_webhook_subscriptions WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): WebhookSubscription
    {
        return $this->find($id) ?? throw new RuntimeException("Webhook subscription \"{$id}\" was not found.");
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof WebhookSubscription) {
            throw new InvalidArgumentException('WebhookSubscriptionRepository::save() expects a WebhookSubscription.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_webhook_subscriptions
                (uid, organization_id, url, event_pattern, secret, status, consecutive_failures,
                 created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :url, :event_pattern, :secret, :status, :consecutive_failures,
                 :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                url = VALUES(url), event_pattern = VALUES(event_pattern), status = VALUES(status),
                consecutive_failures = VALUES(consecutive_failures), updated_at = VALUES(updated_at),
                version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'url' => $entity->url,
            'event_pattern' => $entity->eventPattern,
            'secret' => $entity->secret,
            'status' => $entity->status,
            'consecutive_failures' => $entity->consecutiveFailures,
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_webhook_subscriptions SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_webhook_subscriptions SET archived_at = NULL WHERE uid = :uid');
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return WebhookSubscription[]
     */
    public function activeForEventPattern(string $eventPattern): array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_webhook_subscriptions
             WHERE event_pattern = :event_pattern AND status = 'active' AND archived_at IS NULL"
        );
        $statement->execute(['event_pattern' => $eventPattern]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Every distinct event a currently-active subscription cares about —
     * what `KontorAPI::init()` subscribes onto Core's EventDispatcher, the
     * same "distinct triggers" approach `kontor/automation`'s
     * `KontorAutomation::init()` already uses for its own rules.
     *
     * @return string[]
     */
    public function distinctActiveEventPatterns(): array
    {
        $statement = $this->pdo->query(
            "SELECT DISTINCT event_pattern FROM kontor_webhook_subscriptions
             WHERE status = 'active' AND archived_at IS NULL"
        );

        return array_column($statement->fetchAll(\PDO::FETCH_ASSOC), 'event_pattern');
    }

    /**
     * @return WebhookSubscription[]
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_webhook_subscriptions
             WHERE organization_id = :organization_id AND archived_at IS NULL
             ORDER BY created_at DESC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): WebhookSubscription
    {
        return new WebhookSubscription(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            url: $row['url'],
            eventPattern: $row['event_pattern'],
            secret: $row['secret'],
            status: $row['status'],
            consecutiveFailures: (int) $row['consecutive_failures'],
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
