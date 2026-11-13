<?php

declare(strict_types=1);

namespace Kontor\API\Contracts;

interface ArchivableApiResourceInterface extends ApiResourceInterface
{
    public function archive(string $organizationId, string $uid): void;
}
