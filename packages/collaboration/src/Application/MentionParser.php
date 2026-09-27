<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Application;

/**
 * The "mentions" milestone's actual parsing, not just storage: extracts
 * `@123`-style ProcessWire user id mentions from a comment body. Numeric
 * ids rather than `@username` because Kontor has no user-lookup service of
 * its own to resolve a username against — the caller (an admin UI with
 * autocomplete) is expected to insert `@<id>` tokens, same way most
 * editors insert a resolved mention rather than leaving raw text to
 * re-parse ambiguously.
 */
final class MentionParser
{
    /**
     * @return int[] unique user ids, in first-appearance order
     */
    public function extract(string $body): array
    {
        if (preg_match_all('/@(\d+)/', $body, $matches) === 0) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $matches[1])));
    }
}
