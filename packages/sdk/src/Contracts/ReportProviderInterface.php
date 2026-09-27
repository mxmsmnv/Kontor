<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\ReportResult;
use Kontor\SDK\DTO\ReportSchema;

interface ReportProviderInterface
{
    public function key(): string;

    public function title(): string;

    public function schema(): ReportSchema;

    public function execute(ReportQuery $query): ReportResult;
}
