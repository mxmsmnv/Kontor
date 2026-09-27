<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\DTO\ValidationResult;

interface ImportProviderInterface
{
    public function entityType(): string;

    /**
     * @return string[]
     */
    public function fields(): array;

    public function validate(array $record, ImportContext $context): ValidationResult;

    public function findExisting(array $record, ImportContext $context): ?string;

    public function import(array $record, ImportContext $context): ImportRecordResult;
}
