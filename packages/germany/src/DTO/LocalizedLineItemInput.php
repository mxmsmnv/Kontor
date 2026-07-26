<?php

declare(strict_types=1);

namespace Kontor\Germany\DTO;

use Kontor\SDK\ValueObjects\Money;

final class LocalizedLineItemInput
{
    public function __construct(
        public readonly string $description,
        public readonly string $quantity,
        public readonly Money $unitPrice,
        public readonly Money $lineTotal,
        public readonly string $taxRatePercent,
    ) {
    }
}
