<?php

declare(strict_types=1);

namespace Kontor\Catalog\Support;

/**
 * Substage 3.2 "units". kontor.md#14.1 has only a plain `unit_code`
 * string column, not a dedicated unit-of-measure table, so this is a
 * lightweight known-code registry rather than a repository — instance-
 * based (not static) like every other registry in this codebase, so a
 * localization package can extend it with its own units via the
 * constructor without mutating shared global state.
 */
final class UnitOfMeasure
{
    private const DEFAULTS = [
        'pcs' => 'Piece',
        'box' => 'Box',
        'pallet' => 'Pallet',
        'set' => 'Set',
        'pair' => 'Pair',
        'kg' => 'Kilogram',
        'g' => 'Gram',
        'l' => 'Liter',
        'ml' => 'Milliliter',
        'm' => 'Meter',
        'cm' => 'Centimeter',
        'm2' => 'Square meter',
        'm3' => 'Cubic meter',
        'hour' => 'Hour',
        'day' => 'Day',
        'week' => 'Week',
        'month' => 'Month',
        'year' => 'Year',
    ];

    /**
     * @var array<string, string> code => English label
     */
    private array $units;

    /**
     * @param array<string, string> $additional extra code => label pairs, override defaults on conflict
     */
    public function __construct(array $additional = [])
    {
        $this->units = [...self::DEFAULTS, ...$additional];
    }

    public function isKnown(string $code): bool
    {
        return isset($this->units[$code]);
    }

    public function label(string $code): ?string
    {
        return $this->units[$code] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->units;
    }
}
