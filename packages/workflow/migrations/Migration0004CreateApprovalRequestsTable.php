<?php

declare(strict_types=1);

namespace Kontor\Workflow\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * The "approvals" milestone: when a transition's requires_approval is
 * set, WorkflowEngine::transition() creates one of these instead of
 * moving the instance's state immediately — the same single-decision
 * shape kontor/expenses' own approval workflow uses, generalized here to
 * any entity/transition.
 */
final class Migration0004CreateApprovalRequestsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'workflow';
    }

    public function name(): string
    {
        return '0004_create_approval_requests_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_workflow_approval_requests (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                instance_uid CHAR(26) NOT NULL,
                action_key VARCHAR(100) NOT NULL,
                from_state VARCHAR(50) NOT NULL,
                to_state VARCHAR(50) NOT NULL,
                requested_by BIGINT UNSIGNED NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                decided_by BIGINT UNSIGNED NULL,
                decided_at DATETIME(6) NULL,
                rejection_reason VARCHAR(255) NULL,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_instance (instance_uid),
                INDEX idx_pending (organization_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_workflow_approval_requests');
    }
}
