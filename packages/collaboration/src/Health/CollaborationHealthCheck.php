<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Health;

use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

final class CollaborationHealthCheck implements HealthCheckInterface
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function key(): string
    {
        return 'collaboration';
    }

    public function run(): HealthCheckResult
    {
        try {
            $comments = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_comments WHERE archived_at IS NULL')->fetchColumn();
            $unreadMentions = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_mentions WHERE read_at IS NULL')->fetchColumn();

            return new HealthCheckResult(
                'ok',
                "{$comments} comment(s), {$unreadMentions} unread mention(s).",
                ['comments' => $comments, 'unreadMentions' => $unreadMentions],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Collaboration tables are not reachable: {$e->getMessage()}");
        }
    }
}
