<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\ComponentContext;

interface ComponentInterface
{
    public function name(): string;

    public function version(): string;

    public function boot(ComponentContext $context): void;

    public function register(ComponentContext $context): void;

    /**
     * @return iterable<HealthCheckInterface>
     */
    public function healthChecks(): iterable;
}
