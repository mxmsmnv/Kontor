<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Domain\AuditEvent;
use Kontor\Core\Infrastructure\ImportExport\Format\CsvRecordWriter;

final class AuditCsvExporter
{
    /**
     * @param iterable<AuditEvent> $events
     */
    public function export(string $path, iterable $events): int
    {
        $count = 0;
        $records = (static function () use ($events, &$count): \Generator {
            foreach ($events as $event) {
                $count++;

                yield [
                    'occurred_at' => $event->occurredAt->format(DATE_ATOM),
                    'event_uid' => $event->uid,
                    'component' => $event->component,
                    'entity_type' => $event->entityType,
                    'entity_uid' => $event->entityUid,
                    'action' => $event->action,
                    'actor_type' => $event->actorType,
                    'actor_uid' => $event->actorUid ?? '',
                    'previous_json' => self::encode($event->previous),
                    'current_json' => self::encode($event->current),
                    'metadata_json' => self::encode($event->metadata),
                ];
            }
        })();

        (new CsvRecordWriter())->write($path, $records, [
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
        ]);

        return $count;
    }

    private static function encode(?array $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) json_encode(
            $value,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
