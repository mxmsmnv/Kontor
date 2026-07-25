<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Persistence;

/**
 * Atomic document numbering over kontor_sequences (kontor.md#11.9) — a
 * table created in Substage 1.2 that had no service until kontor/sales
 * became its first real consumer (needing quotation/order numbers). Same
 * "gap found, filled where the first real consumer needs it" pattern as
 * ExtensionRepository (Substage 3.1) and ReportProviderRegistry
 * (Substage 3.3).
 *
 * next() locks the sequence row for the duration of the increment (via
 * SELECT ... FOR UPDATE in a transaction), the same locking technique
 * kontor/queue's JobRepository::reserveNext() uses, so concurrent callers
 * never receive the same number.
 */
final class SequenceService
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    /**
     * $prefix/$suffix/$padding/$resetPolicy only take effect the first time
     * a given (organization, component, sequenceKey) sequence is used —
     * that call creates its row and fixes its configuration. Every later
     * call ignores these parameters and uses what's already stored, so a
     * caller that forgets to pass them (or passes different ones) can
     * never silently change an existing sequence's configuration.
     *
     * @param 'never'|'yearly'|'monthly'|'daily' $resetPolicy
     */
    public function next(
        string $organizationUid,
        string $component,
        string $sequenceKey,
        ?string $prefix = null,
        ?string $suffix = null,
        int $padding = 5,
        string $resetPolicy = 'never',
    ): string {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $wasInTransaction = $this->pdo->inTransaction();

        if (!$wasInTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $statement = $this->pdo->prepare(
                'SELECT * FROM kontor_sequences
                 WHERE organization_id = :organization_id AND component = :component AND sequence_key = :sequence_key
                 FOR UPDATE'
            );
            $statement->execute([
                'organization_id' => $organizationId,
                'component' => $component,
                'sequence_key' => $sequenceKey,
            ]);
            $row = $statement->fetch(\PDO::FETCH_ASSOC);

            if ($row === false) {
                $nextNumber = 1;
                $currentMarker = $this->periodMarker($resetPolicy);

                $insert = $this->pdo->prepare(
                    'INSERT INTO kontor_sequences
                        (organization_id, component, sequence_key, prefix, suffix, next_number, padding,
                         reset_policy, reset_marker, updated_at, version)
                     VALUES
                        (:organization_id, :component, :sequence_key, :prefix, :suffix, :next_number, :padding,
                         :reset_policy, :reset_marker, :updated_at, 1)'
                );
                $insert->execute([
                    'organization_id' => $organizationId,
                    'component' => $component,
                    'sequence_key' => $sequenceKey,
                    'prefix' => $prefix,
                    'suffix' => $suffix,
                    'next_number' => $nextNumber + 1,
                    'padding' => $padding,
                    'reset_policy' => $resetPolicy,
                    'reset_marker' => $currentMarker,
                    'updated_at' => $this->now(),
                ]);
            } else {
                $prefix = $row['prefix'];
                $suffix = $row['suffix'];
                $padding = (int) $row['padding'];
                $currentMarker = $this->periodMarker($row['reset_policy']);

                $nextNumber = (int) $row['next_number'];

                if ($row['reset_policy'] !== 'never' && $row['reset_marker'] !== $currentMarker) {
                    $nextNumber = 1;
                }

                $update = $this->pdo->prepare(
                    'UPDATE kontor_sequences
                     SET next_number = :next_number, reset_marker = :reset_marker, updated_at = :updated_at,
                         version = version + 1
                     WHERE id = :id'
                );
                $update->execute([
                    'next_number' => $nextNumber + 1,
                    'reset_marker' => $currentMarker,
                    'updated_at' => $this->now(),
                    'id' => $row['id'],
                ]);
            }

            if (!$wasInTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if (!$wasInTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }

        return ($prefix ?? '') . str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT) . ($suffix ?? '');
    }

    private function periodMarker(string $resetPolicy): ?string
    {
        return match ($resetPolicy) {
            'yearly' => date('Y'),
            'monthly' => date('Y-m'),
            'daily' => date('Y-m-d'),
            default => null,
        };
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
