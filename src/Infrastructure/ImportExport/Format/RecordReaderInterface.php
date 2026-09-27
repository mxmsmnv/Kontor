<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\ImportExport\Format;

/**
 * Turns a file into a stream of associative-array records keyed by column
 * name (kontor.md#25 required import formats). Every reader yields plain
 * strings (or null for empty cells) regardless of format — type coercion
 * is the concern of the ImportProviderInterface's validate(), not the
 * format layer.
 */
interface RecordReaderInterface
{
    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function read(string $path): iterable;
}
