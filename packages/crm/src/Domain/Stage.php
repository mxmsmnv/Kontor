<?php

declare(strict_types=1);

namespace Kontor\CRM\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * kontor.md#13.3. name_key is the machine-stable identifier (section 23:
 * "Machine identifiers remain English and stable"); display_name is what
 * a Kanban column header actually shows, per locale.
 */
final class Stage
{
    /**
     * @param array<string, string> $displayName locale => text
     * @param array<string, mixed> $rules
     */
    public function __construct(
        public readonly Uid $uid,
        public readonly string $pipelineUid,
        public string $nameKey,
        public array $displayName,
        public int $probability,
        public int $sortOrder,
        public string $stateType,
        public ?string $color,
        public array $rules = [],
    ) {
    }

    /**
     * @param array<string, string> $displayName
     * @param array<string, mixed> $rules
     */
    public static function create(
        string $pipelineUid,
        string $nameKey,
        array $displayName,
        int $probability = 0,
        int $sortOrder = 0,
        string $stateType = 'open',
        ?string $color = null,
        array $rules = [],
    ): self {
        return new self(
            uid: Uid::generate(),
            pipelineUid: $pipelineUid,
            nameKey: $nameKey,
            displayName: $displayName,
            probability: $probability,
            sortOrder: $sortOrder,
            stateType: $stateType,
            color: $color,
            rules: $rules,
        );
    }

    public function displayNameIn(string $locale, string $fallback = 'en'): ?string
    {
        return $this->displayName[$locale] ?? $this->displayName[$fallback] ?? null;
    }

    public function isWon(): bool
    {
        return $this->stateType === 'won';
    }

    public function isLost(): bool
    {
        return $this->stateType === 'lost';
    }
}
