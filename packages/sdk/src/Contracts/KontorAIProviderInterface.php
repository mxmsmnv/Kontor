<?php

declare(strict_types=1);

namespace Kontor\SDK\Contracts;

use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;

interface KontorAIProviderInterface
{
    public function supports(string $capability): bool;

    public function execute(AIRequest $request): AIResponse;
}
