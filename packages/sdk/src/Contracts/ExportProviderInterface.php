<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\ExportContext;

interface ExportProviderInterface
{
    public function entityType(): string;

    /**
     * @return string[]
     */
    public function fields(): array;

    /**
     * @return string[]
     */
    public function filters(): array;

    public function count(array $filters, ExportContext $context): int;

    /**
     * @return iterable<array<string, mixed>>
     */
    public function iterate(
        array $filters,
        array $fields,
        ExportContext $context
    ): iterable;
}
