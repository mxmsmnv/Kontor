<?php

declare(strict_types=1);

namespace Kontor\Marketplace\DTO;

use Kontor\Core\Domain\DependencyCheckResult;
use Kontor\Marketplace\Domain\Advisory;

final class InstallabilityResult
{
    /**
     * @param Advisory[] $advisories every known advisory affecting this exact version
     */
    public function __construct(
        public readonly DependencyCheckResult $dependencies,
        public readonly array $advisories,
    ) {
    }

    /**
     * Installable when dependencies are satisfied and no *critical*
     * advisory affects this exact version — a lower-severity advisory is
     * surfaced (via `$advisories`) but doesn't block installation on its
     * own.
     */
    public function isInstallable(): bool
    {
        return $this->dependencies->satisfied && !$this->hasCriticalAdvisory();
    }

    public function hasCriticalAdvisory(): bool
    {
        foreach ($this->advisories as $advisory) {
            if ($advisory->severity === 'critical') {
                return true;
            }
        }

        return false;
    }
}
