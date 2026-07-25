<?php

declare(strict_types=1);

namespace Kontor\API\Infrastructure\Persistence;

use Kontor\API\Contracts\IdempotencyStoreInterface;
use Kontor\API\DTO\StoredIdempotentResponse;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class IdempotencyKeyRepository implements IdempotencyStoreInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    /**
     * @return array{response: StoredIdempotentResponse, requestFingerprint: string}|null
     */
    public function find(string $organizationUid, string $idempotencyKey): ?array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_idempotency_keys
             WHERE organization_id = :organization_id AND idempotency_key = :idempotency_key
               AND (expires_at IS NULL OR expires_at > :now)'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'idempotency_key' => $idempotencyKey,
            'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return [
            'response' => new StoredIdempotentResponse(
                status: (int) $row['response_status'],
                body: json_decode($row['response_json'], associative: true, flags: JSON_THROW_ON_ERROR),
            ),
            'requestFingerprint' => $row['request_fingerprint'],
        ];
    }

    /**
     * @param array<string, mixed> $responseBody
     */
    public function store(
        string $organizationUid,
        string $idempotencyKey,
        string $requestFingerprint,
        int $responseStatus,
        array $responseBody,
        ?\DateTimeImmutable $expiresAt = null,
    ): void {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_idempotency_keys
                (organization_id, idempotency_key, request_fingerprint, response_status, response_json,
                 created_at, expires_at)
             VALUES
                (:organization_id, :idempotency_key, :request_fingerprint, :response_status, :response_json,
                 :created_at, :expires_at)'
        );

        $statement->execute([
            'organization_id' => $organizationId,
            'idempotency_key' => $idempotencyKey,
            'request_fingerprint' => $requestFingerprint,
            'response_status' => $responseStatus,
            'response_json' => json_encode($responseBody, JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            'expires_at' => $expiresAt?->format('Y-m-d H:i:s.u'),
        ]);
    }
}
