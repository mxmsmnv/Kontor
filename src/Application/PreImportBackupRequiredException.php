<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use RuntimeException;

/**
 * kontor.md#25: a live (non-dry-run) import requires a verified pre-import
 * backup first, unless the actor holds kontor-updates-bypass-backup —
 * mirrors PreUpdateBackupRequiredException from Substage 1.4.
 */
final class PreImportBackupRequiredException extends RuntimeException
{
}
