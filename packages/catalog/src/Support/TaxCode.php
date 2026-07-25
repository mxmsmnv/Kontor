<?php

declare(strict_types=1);

namespace Kontor\Catalog\Support;

/**
 * Substage 3.2 "tax references". kontor.md#14.1's `tax_code` is a plain
 * string, not a link to a tax-rate entity — actual tax RATES are a
 * localization concern (spec section 5.4: KontorGermany, KontorUSA, ...),
 * out of scope here. This only validates that a code is one of the
 * generic categories every jurisdiction maps its own rates onto.
 */
final class TaxCode
{
    private const DEFAULTS = [
        'standard' => 'Standard rate',
        'reduced' => 'Reduced rate',
        'zero' => 'Zero rate',
        'exempt' => 'Exempt',
        'reverse_charge' => 'Reverse charge',
    ];

    /**
     * @var array<string, string>
     */
    private array $codes;

    /**
     * @param array<string, string> $additional extra code => label pairs, override defaults on conflict
     */
    public function __construct(array $additional = [])
    {
        $this->codes = [...self::DEFAULTS, ...$additional];
    }

    public function isKnown(string $code): bool
    {
        return isset($this->codes[$code]);
    }

    public function label(string $code): ?string
    {
        return $this->codes[$code] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->codes;
    }
}
