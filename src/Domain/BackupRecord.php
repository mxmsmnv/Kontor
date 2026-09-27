<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

/**
 * The result of BackupManager::create() — a backup that has already been
 * exported and verified in one step (kontor.md#24: "create backup; verify
 * backup; mark backup Verified; only then continue").
 */
final class BackupRecord
{
    /**
     * @param string[] $verificationErrors
     */
    public function __construct(
        public readonly string $id,
        public readonly string $component,
        public readonly string $kind,
        public readonly string $path,
        public readonly \DateTimeImmutable $createdAt,
        public readonly int $itemCount,
        public readonly int $estimatedSizeBytes,
        public readonly bool $verified,
        public readonly ?string $checksum,
        public readonly array $verificationErrors = [],
    ) {
    }
}
