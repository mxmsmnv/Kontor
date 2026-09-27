<?php

declare(strict_types=1);

namespace Kontor\Queue\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#11.5
 */
final class Migration0001CreateJobsTable implements MigrationInterface
{
    public function component(): string
    {
        return 'queue';
    }

    public function name(): string
    {
        return '0001_create_jobs_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                queue VARCHAR(191) NOT NULL DEFAULT 'default',
                job_type VARCHAR(191) NOT NULL,
                payload_json JSON NULL,
                priority INT NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                max_attempts INT UNSIGNED NOT NULL DEFAULT 3,
                available_at DATETIME(6) NOT NULL,
                started_at DATETIME(6) NULL,
                finished_at DATETIME(6) NULL,
                failed_at DATETIME(6) NULL,
                progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
                idempotency_key VARCHAR(191) NULL,
                error_message TEXT NULL,
                created_at DATETIME(6) NOT NULL,
                UNIQUE KEY uniq_uid (uid),
                UNIQUE KEY uniq_idempotency_key (idempotency_key),
                INDEX idx_reservation (queue, status, priority, available_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_jobs');
    }
}
