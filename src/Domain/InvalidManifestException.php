<?php

declare(strict_types=1);

namespace Kontor\Core\Domain;

use RuntimeException;

/**
 * A kontor.json does not satisfy the manifest requirements of
 * kontor.md#22.2 (missing required field, malformed structure).
 */
final class InvalidManifestException extends RuntimeException
{
}
