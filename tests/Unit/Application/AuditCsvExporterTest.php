<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Application;

use Kontor\Core\Application\AuditCsvExporter;
use Kontor\Core\Domain\AuditEvent;
use PHPUnit\Framework\TestCase;

final class AuditCsvExporterTest extends TestCase
{
    public function test_exports_a_stable_audit_csv_schema(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kontor_audit_');
        $this->assertNotFalse($path);

        try {
            $event = new AuditEvent(
                uid: 'event_01',
                component: 'contacts',
                entityType: 'contact',
                entityUid: 'contact_01',
                action: 'updated',
                actorType: 'user',
                actorUid: 'user_01',
                occurredAt: new \DateTimeImmutable('2026-07-26T12:30:00+00:00'),
                previous: ['name' => 'Ada'],
                current: ['name' => 'Ada Lovelace'],
                metadata: ['source' => 'admin'],
            );

            $count = (new AuditCsvExporter())->export($path, [$event]);
            $rows = array_map(
                static fn (string $line): array => str_getcsv($line, ',', '"', '\\'),
                file($path, FILE_IGNORE_NEW_LINES)
            );

            $this->assertSame(1, $count);
            $this->assertSame([
                'occurred_at',
                'event_uid',
                'component',
                'entity_type',
                'entity_uid',
                'action',
                'actor_type',
                'actor_uid',
                'previous_json',
                'current_json',
                'metadata_json',
            ], $rows[0]);
            $this->assertSame('2026-07-26T12:30:00+00:00', $rows[1][0]);
            $this->assertSame('contact_01', $rows[1][4]);
            $this->assertSame('{"name":"Ada Lovelace"}', $rows[1][9]);
            $this->assertSame('{"source":"admin"}', $rows[1][10]);
        } finally {
            if (is_string($path) && is_file($path)) {
                unlink($path);
            }
        }
    }
}
