<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use RuntimeException;

/**
 * kontor.md#24: no component update may proceed without a verified backup,
 * unless the actor holds `kontor-updates-bypass-backup` (kontor.md#19.2).
 */
final class PreUpdateBackupRequiredException extends RuntimeException
{
}
