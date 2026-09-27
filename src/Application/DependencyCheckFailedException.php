<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

use Kontor\Core\Domain\DependencyCheckResult;
use RuntimeException;

final class DependencyCheckFailedException extends RuntimeException
{
    public function __construct(public readonly DependencyCheckResult $result)
    {
        parent::__construct('Dependency check failed: ' . implode('; ', $result->problems()));
    }
}
