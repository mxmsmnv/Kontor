<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\HealthCheckResult;

interface HealthCheckInterface
{
    public function key(): string;

    public function run(): HealthCheckResult;
}
