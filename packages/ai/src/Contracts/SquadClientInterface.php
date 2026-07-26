<?php

declare(strict_types=1);

namespace Kontor\AI\Contracts;

/**
 * The boundary a real Kontor deployment implements against Squad's
 * actual API. kontor.md#32: "Kontor must not depend directly on internal
 * Squad implementation details" — this package deliberately has no
 * knowledge of Squad's real wire protocol; `SquadAdapter` depends only on
 * this interface, the same injectable-I/O-boundary pattern
 * `kontor/api`'s `HttpClientInterface`, `kontor/marketplace`'s
 * `RegistryClientInterface`, and `kontor/mail`'s `MailSenderInterface`
 * already use for their own external dependencies.
 */
interface SquadClientInterface
{
    /**
     * @param array<string, mixed> $input
     * @return array{output: array<string, mixed>, requiresConfirmation?: bool}
     *
     * @throws \RuntimeException if Squad could not fulfill the request
     */
    public function complete(string $capability, array $input): array;
}
