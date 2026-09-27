<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\Domain\ApiToken;
use Kontor\API\DTO\IssuedApiToken;
use Kontor\API\Infrastructure\Persistence\ApiTokenRepository;

/**
 * The "authentication" milestone (kontor.md#20.1 "scoped API token" /
 * "bearer token" — the same value plays both roles here, presented as an
 * `Authorization: Bearer <token>` header). ProcessWire session
 * authentication is a separate path handled at the HTTP-glue layer
 * (`ApiRequestHandler`), since it depends on the live `$session`/`$user`
 * API this class deliberately has no dependency on.
 */
final class TokenAuthenticator
{
    private const TOKEN_PREFIX = 'kontor_';

    public function __construct(
        private readonly ApiTokenRepository $tokens,
    ) {
    }

    /**
     * @param string[] $scopes empty means unrestricted
     */
    public function issue(
        string $organizationId,
        string $name,
        array $scopes = [],
        ?\DateTimeImmutable $expiresAt = null,
        ?int $createdBy = null,
    ): IssuedApiToken {
        $plaintext = self::TOKEN_PREFIX.bin2hex(random_bytes(24));
        $token = ApiToken::create($organizationId, $name, self::hash($plaintext), $scopes, $expiresAt, $createdBy);

        $this->tokens->save($token);

        return new IssuedApiToken($token, $plaintext);
    }

    /**
     * @throws AuthenticationFailedException
     */
    public function authenticate(string $plaintextToken, ?string $requiredScope = null): ApiToken
    {
        $token = $this->tokens->findByTokenHash(self::hash($plaintextToken));

        if ($token === null) {
            throw new AuthenticationFailedException('Invalid API token.');
        }

        if (!$token->isActive()) {
            throw new AuthenticationFailedException('This API token has been revoked.');
        }

        if ($token->isExpired()) {
            throw new AuthenticationFailedException('This API token has expired.');
        }

        if ($requiredScope !== null && !$token->hasScope($requiredScope)) {
            throw new AuthenticationFailedException("This API token does not have the \"{$requiredScope}\" scope.");
        }

        $token->recordUsage();
        $this->tokens->save($token);

        return $token;
    }

    public function revoke(string $tokenUid): void
    {
        $this->tokens->archive($tokenUid);
    }

    private static function hash(string $plaintextToken): string
    {
        return hash('sha256', $plaintextToken);
    }
}
