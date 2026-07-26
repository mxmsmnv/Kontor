<?php

declare(strict_types=1);

namespace Kontor\AI\Infrastructure\Providers;

use Kontor\AI\Contracts\SquadClientInterface;
use RuntimeException;

/**
 * The one trivial built-in `SquadClientInterface` implementation,
 * proving the adapter pipeline end-to-end when no real Squad connection
 * is configured — the same "built once, adopted by whoever wants it
 * next" precedent every other registry/adapter extension point in this
 * monorepo follows (e.g. `RawEmailForwardAdapter`). A real deployment
 * supplies its own `SquadClientInterface` implementation wired to
 * Squad's actual API; kontor.md#32 itself says "Kontor AI is optional",
 * so a clean, explicit failure here — rather than a fabricated response —
 * is the correct default behavior.
 */
final class NullSquadClient implements SquadClientInterface
{
    public function complete(string $capability, array $input): array
    {
        throw new RuntimeException("No Squad connection is configured to handle capability \"{$capability}\".");
    }
}
