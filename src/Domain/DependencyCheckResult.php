<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

final class DependencyCheckResult
{
    /**
     * @param string[] $missing human-readable unmet requirements
     * @param string[] $conflicts human-readable conflicting packages
     */
    private function __construct(
        public readonly bool $satisfied,
        public readonly array $missing = [],
        public readonly array $conflicts = [],
    ) {
    }

    public static function ok(): self
    {
        return new self(true);
    }

    /**
     * @param string[] $missing
     * @param string[] $conflicts
     */
    public static function failed(array $missing, array $conflicts): self
    {
        return new self(false, $missing, $conflicts);
    }

    /**
     * @return string[]
     */
    public function problems(): array
    {
        return [...$this->missing, ...$this->conflicts];
    }
}
