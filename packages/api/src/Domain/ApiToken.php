<?php

declare(strict_types=1);

namespace Kontor\API\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#20.1 "scoped API token". Only `tokenHash` (SHA-256 of the
 * plaintext) is ever persisted — see `TokenAuthenticator::issue()` for
 * where the one-time plaintext is generated and returned to the caller.
 */
final class ApiToken
{
    /**
     * @param string[] $scopes
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public string $name,
        public readonly string $tokenHash,
        public array $scopes,
        public string $status,
        public ?\DateTimeImmutable $lastUsedAt,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
        public readonly ?int $createdBy,
    ) {
    }

    /**
     * @param string[] $scopes
     */
    public static function create(
        string $organizationId,
        string $name,
        string $tokenHash,
        array $scopes = [],
        ?\DateTimeImmutable $expiresAt = null,
        ?int $createdBy = null,
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            name: $name,
            tokenHash: $tokenHash,
            scopes: $scopes,
            status: 'active',
            lastUsedAt: null,
            expiresAt: $expiresAt,
            createdAt: $now,
            updatedAt: $now,
            createdBy: $createdBy,
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < ($now ?? new \DateTimeImmutable());
    }

    public function hasScope(string $scope): bool
    {
        // An empty scopes list means unrestricted, same as omitting `scope` entirely when issuing.
        return $this->scopes === [] || in_array($scope, $this->scopes, strict: true);
    }

    public function recordUsage(): void
    {
        $this->lastUsedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->lastUsedAt;
    }
}
