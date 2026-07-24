<?php

declare(strict_types=1);

namespace Kontor\Core\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md#11.9
 */
final class Migration0004CreateSequencesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'core';
    }

    public function name(): string
    {
        return '0004_create_sequences_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_sequences (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                organization_id BIGINT UNSIGNED NOT NULL,
                component VARCHAR(191) NOT NULL,
                sequence_key VARCHAR(191) NOT NULL,
                prefix VARCHAR(32) NULL,
                suffix VARCHAR(32) NULL,
                next_number BIGINT UNSIGNED NOT NULL DEFAULT 1,
                padding TINYINT UNSIGNED NOT NULL DEFAULT 0,
                reset_policy VARCHAR(20) NOT NULL DEFAULT 'never',
                reset_marker VARCHAR(20) NULL,
                updated_at DATETIME(6) NOT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                UNIQUE KEY uniq_org_component_key (organization_id, component, sequence_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_sequences');
    }
}
