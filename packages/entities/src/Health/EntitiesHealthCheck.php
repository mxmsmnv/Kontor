<?php

declare(strict_types=1);

namespace Kontor\Entities\Health;

use Kontor\Entities\Infrastructure\Persistence\EntityRecordRepository;
use Kontor\SDK\Contracts\HealthCheckInterface;
use Kontor\SDK\DTO\HealthCheckResult;

/**
 * Beyond a plain count, checks a real invariant: no record should
 * reference a definition that's missing or archived — EntityRecordService
 * always validates against a live definition's fields at write time, so a
 * mismatch means the definition was archived out from under existing
 * records (allowed — archiving is reversible, kontor.md#10.4 — but worth
 * surfacing).
 */
final class EntitiesHealthCheck implements HealthCheckInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly EntityRecordRepository $records,
    ) {
    }

    public function key(): string
    {
        return 'entities';
    }

    public function run(): HealthCheckResult
    {
        try {
            $definitions = (int) $this->pdo->query("SELECT COUNT(*) FROM kontor_entity_definitions WHERE status = 'active' AND archived_at IS NULL")->fetchColumn();
            $orphaned = $this->records->countOrphaned();

            if ($orphaned > 0) {
                return new HealthCheckResult(
                    'warning',
                    "{$orphaned} record(s) reference a missing or archived entity definition.",
                    ['activeDefinitions' => $definitions, 'orphanedRecords' => $orphaned],
                );
            }

            return new HealthCheckResult(
                'ok',
                "{$definitions} active entity definition(s), no orphaned records.",
                ['activeDefinitions' => $definitions, 'orphanedRecords' => 0],
            );
        } catch (\Throwable $e) {
            return new HealthCheckResult('critical', "Entities tables are not reachable: {$e->getMessage()}");
        }
    }
}
