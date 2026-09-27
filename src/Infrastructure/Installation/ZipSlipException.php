<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Installation;

use RuntimeException;

/**
 * A component ZIP contains an entry that would extract outside the target
 * directory (path traversal / "zip slip") or exceeds the configured size
 * limits. Codex rule #18: destructive/unsafe operations need a guard rail,
 * not an assumption of well-formed input.
 */
final class ZipSlipException extends RuntimeException
{
}
