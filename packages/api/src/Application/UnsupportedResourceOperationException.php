<?php

declare(strict_types=1);

namespace Kontor\API\Application;

final class UnsupportedResourceOperationException extends \RuntimeException
{
    public static function forResource(string $resourceKey, string $operation): self
    {
        return new self("Resource \"{$resourceKey}\" does not support \"{$operation}\".");
    }
}
