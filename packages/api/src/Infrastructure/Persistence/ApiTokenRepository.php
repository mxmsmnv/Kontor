<?php

declare(strict_types=1);

namespace Kontor\API\Infrastructure\Persistence;

use InvalidArgumentException;
use Kontor\API\Domain\ApiToken;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\ValueObjects\Uid;
use RuntimeException;

final class ApiTokenRepository implements RepositoryInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function find(string $id): ?ApiToken
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_api_tokens WHERE uid = :uid');
        $statement->execute(['uid' => $id]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $id): ApiToken
    {
        return $this->find($id) ?? throw new RuntimeException("API token \"{$id}\" was not found.");
    }

    /**
     * The authentication lookup path — never queried by uid, since the
     * caller only ever presents the plaintext token (hashed by
     * TokenAuthenticator before reaching here).
     */
    public function findByTokenHash(string $tokenHash): ?ApiToken
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_api_tokens WHERE token_hash = :token_hash');
        $statement->execute(['token_hash' => $tokenHash]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function save(object $entity): void
    {
        if (!$entity instanceof ApiToken) {
            throw new InvalidArgumentException('ApiTokenRepository::save() expects an ApiToken.');
        }

        $organizationId = $this->organizations->internalIdOf($entity->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_api_tokens
                (uid, organization_id, name, token_hash, scopes_json, status, last_used_at, expires_at,
                 created_at, updated_at, created_by, version)
             VALUES
                (:uid, :organization_id, :name, :token_hash, :scopes_json, :status, :last_used_at, :expires_at,
                 :created_at, :updated_at, :created_by, 1)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), scopes_json = VALUES(scopes_json), status = VALUES(status),
                last_used_at = VALUES(last_used_at), updated_at = VALUES(updated_at), version = version + 1'
        );

        $statement->execute([
            'uid' => $entity->uid->toString(),
            'organization_id' => $organizationId,
            'name' => $entity->name,
            'token_hash' => $entity->tokenHash,
            'scopes_json' => $entity->scopes !== [] ? json_encode($entity->scopes, JSON_THROW_ON_ERROR) : null,
            'status' => $entity->status,
            'last_used_at' => $entity->lastUsedAt?->format('Y-m-d H:i:s.u'),
            'expires_at' => $entity->expiresAt?->format('Y-m-d H:i:s.u'),
            'created_at' => $entity->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $entity->updatedAt->format('Y-m-d H:i:s.u'),
            'created_by' => $entity->createdBy,
        ]);
    }

    public function archive(string $id): void
    {
        $statement = $this->pdo->prepare("UPDATE kontor_api_tokens SET archived_at = :now, status = 'revoked' WHERE uid = :uid");
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $id]);
    }

    public function restore(string $id): void
    {
        $statement = $this->pdo->prepare("UPDATE kontor_api_tokens SET archived_at = NULL, status = 'active' WHERE uid = :uid");
        $statement->execute(['uid' => $id]);
    }

    /**
     * @return ApiToken[]
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare('SELECT * FROM kontor_api_tokens WHERE organization_id = :organization_id ORDER BY name ASC');
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): ApiToken
    {
        return new ApiToken(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            name: $row['name'],
            tokenHash: $row['token_hash'],
            scopes: $row['scopes_json'] !== null ? json_decode($row['scopes_json'], associative: true, flags: JSON_THROW_ON_ERROR) : [],
            status: $row['status'],
            lastUsedAt: $row['last_used_at'] !== null ? new \DateTimeImmutable($row['last_used_at']) : null,
            expiresAt: $row['expires_at'] !== null ? new \DateTimeImmutable($row['expires_at']) : null,
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
