<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Recovery;

use RuntimeException;

/**
 * Tracks recovery/maintenance state as a filesystem marker rather than a
 * database row (kontor.md diagram 17.4 "Enable maintenance state"):
 * recovery mode exists precisely for situations where the database is
 * mid-restore or otherwise untrustworthy, so its own state can't depend on
 * that same database.
 */
final class RecoveryModeManager
{
    private const MARKER_FILENAME = 'RECOVERY_MODE.json';

    public function __construct(private readonly string $stateDir)
    {
    }

    public function enable(string $reason): void
    {
        if (!is_dir($this->stateDir) && !mkdir($this->stateDir, 0770, true) && !is_dir($this->stateDir)) {
            throw new RuntimeException("Could not create recovery state directory \"{$this->stateDir}\".");
        }

        file_put_contents($this->markerPath(), json_encode([
            'reason' => $reason,
            'enteredAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ], JSON_THROW_ON_ERROR));
    }

    public function disable(): void
    {
        if (is_file($this->markerPath())) {
            unlink($this->markerPath());
        }
    }

    public function isActive(): bool
    {
        return is_file($this->markerPath());
    }

    public function reason(): ?string
    {
        if (!$this->isActive()) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->markerPath()), associative: true, flags: JSON_THROW_ON_ERROR);

        return $data['reason'] ?? null;
    }

    public function enteredAt(): ?\DateTimeImmutable
    {
        if (!$this->isActive()) {
            return null;
        }

        $data = json_decode((string) file_get_contents($this->markerPath()), associative: true, flags: JSON_THROW_ON_ERROR);

        return isset($data['enteredAt']) ? new \DateTimeImmutable($data['enteredAt']) : null;
    }

    private function markerPath(): string
    {
        return rtrim($this->stateDir, '/\\') . DIRECTORY_SEPARATOR . self::MARKER_FILENAME;
    }
}
