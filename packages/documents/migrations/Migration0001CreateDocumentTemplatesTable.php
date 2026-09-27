<?php

declare(strict_types=1);

namespace Kontor\Documents\Migrations;

use Kontor\Core\Infrastructure\Migrations\MigrationInterface;

/**
 * kontor.md section 26 lists KontorDocuments' features but, like Cache and
 * Search before it, the spec has no dedicated schema section for it — this
 * table is a gap-fill. Versions of "the same template" are modeled exactly
 * like kontor_files (kontor.md#11.6): separate rows sharing a family key —
 * here (organization_id, template_key, language) — with an incrementing
 * version_number; superseding a version archives the old row rather than
 * deleting it (codex rule #10). template_key is the stable name other
 * components reference (e.g. quotation/kontor_sales_quotations.template_uid
 * points at a specific row's uid, not at template_key, so a quotation keeps
 * rendering from the exact version it was issued against even after a
 * newer version is published).
 */
final class Migration0001CreateDocumentTemplatesTable implements MigrationInterface
{
    public function component(): string
    {
        return 'documents';
    }

    public function name(): string
    {
        return '0001_create_document_templates_table';
    }

    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS kontor_documents_templates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uid CHAR(26) NOT NULL,
                organization_id BIGINT UNSIGNED NOT NULL,
                template_key VARCHAR(100) NOT NULL,
                document_type VARCHAR(30) NOT NULL,
                language VARCHAR(10) NOT NULL DEFAULT 'en',
                name VARCHAR(255) NOT NULL,
                body_html LONGTEXT NOT NULL,
                custom_css LONGTEXT NULL,
                version_number INT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME(6) NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                archived_at DATETIME(6) NULL,
                UNIQUE KEY uniq_uid (uid),
                INDEX idx_organization_id (organization_id),
                INDEX idx_version_family (organization_id, template_key, language, version_number)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS kontor_documents_templates');
    }
}
