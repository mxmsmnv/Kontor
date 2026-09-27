<?php

declare(strict_types=1);

namespace Kontor\API\DTO;

final class ApiRouteMatch
{
    public function __construct(
        public readonly string $resourceKey,
        public readonly string $action,
        public readonly ?string $uid,
    ) {
    }
}
