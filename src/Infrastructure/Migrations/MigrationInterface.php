<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Migrations;

/**
 * A single reversible schema change, tracked per-component in
 * kontor_migrations (kontor.md#11.3).
 */
interface MigrationInterface
{
    public function component(): string;

    public function name(): string;

    public function up(\PDO $pdo): void;

    public function down(\PDO $pdo): void;
}
