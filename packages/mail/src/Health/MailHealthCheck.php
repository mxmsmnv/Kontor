<?php

declare(strict_types=1);

namespace Kontor\Mail\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Two real invariants, not just a row count: outbound messages that
 * failed to send, and inbound messages nobody has claimed from a shared
 * mailbox — the same "check for a real anomaly" approach
 * `kontor/entities`'/`kontor/api`'s own health checks use.
 */
final class MailHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly \PDO $pdo,
    ) {
    }

    public function key(): string
    {
        return 'mail';
    }

    public function run(): HealthCheckResult
    {
        try {
            $failedOutbound = (int) $this->pdo
                ->query("SELECT COUNT(*) FROM kontor_mail_messages WHERE direction = 'outbound' AND status = 'failed' AND archived_at IS NULL")
                ->fetchColumn();

            $unassignedInbound = (int) $this->pdo
                ->query("SELECT COUNT(*) FROM kontor_mail_messages WHERE direction = 'inbound' AND assigned_to IS NULL AND archived_at IS NULL")
                ->fetchColumn();

            if ($failedOutbound === 0 && $unassignedInbound === 0) {
                return new HealthCheckResult(
                    'ok',
                    'No failed outbound messages or unassigned inbound messages.',
                    ['failedOutbound' => 0, 'unassignedInbound' => 0],
                );
            }

            return new HealthCheckResult(
                'warning',
                "{$failedOutbound} failed outbound message(s), {$unassignedInbound} unassigned inbound message(s).",
                ['failedOutbound' => $failedOutbound, 'unassignedInbound' => $unassignedInbound],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Mail tables are not reachable: {$e->getMessage()}");
        }
    }
}
