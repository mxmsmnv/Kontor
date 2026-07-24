<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Infrastructure\ImportExport\Format\RecordWriterInterface;
use Kontor\Core\Infrastructure\Registry\ExportProviderRegistry;
use Kontor\SDK\DTO\ExportContext;

/**
 * Streams a registered ExportProviderInterface's rows into a
 * RecordWriterInterface (Substage 1.5). Machine vs. localized human-
 * readable mode and locale are carried on ExportContext and are entirely
 * the provider's concern — the manager just passes it through.
 */
final class ExportManager
{
    public function __construct(private readonly ExportProviderRegistry $providers)
    {
    }

    /**
     * @param array<string, mixed> $filters
     * @param string[] $fields empty means "use every field the provider declares"
     * @return int total matching rows per the provider's own count(), independent of what was written
     */
    public function run(
        string $entityType,
        array $filters,
        array $fields,
        ExportContext $context,
        RecordWriterInterface $writer,
        string $path,
    ): int {
        $provider = $this->providers->get($entityType);
        $resolvedFields = $fields === [] ? $provider->fields() : $fields;

        $writer->write($path, $provider->iterate($filters, $resolvedFields, $context), $resolvedFields);

        return $provider->count($filters, $context);
    }
}
