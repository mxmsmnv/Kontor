<?php

declare(strict_types=1);

namespace Kontor\SDK\ValueObjects;

use InvalidArgumentException;

/**
 * Money is always stored and computed in minor units (kontor.md#10.5).
 * PHP float must never represent money — this class is the only sanctioned
 * arithmetic surface for monetary amounts.
 */
final class Money implements \Stringable
{
    private function __construct(
        private readonly int $amountMinor,
        private readonly string $currencyCode,
    ) {
        if (strlen($currencyCode) !== 3) {
            throw new InvalidArgumentException("\"{$currencyCode}\" is not a valid ISO 4217 currency code.");
        }
    }

    public static function ofMinor(int $amountMinor, string $currencyCode): self
    {
        return new self($amountMinor, strtoupper($currencyCode));
    }

    public static function zero(string $currencyCode): self
    {
        return new self(0, strtoupper($currencyCode));
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor + $other->amountMinor, $this->currencyCode);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountMinor - $other->amountMinor, $this->currencyCode);
    }

    public function multiply(int|float $factor): self
    {
        return new self((int) round($this->amountMinor * $factor), $this->currencyCode);
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function isNegative(): bool
    {
        return $this->amountMinor < 0;
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor && $this->currencyCode === $other->currencyCode;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currencyCode !== $other->currencyCode) {
            throw new InvalidArgumentException(
                "Cannot operate on Money in different currencies ({$this->currencyCode} vs {$other->currencyCode})."
            );
        }
    }

    public function toString(): string
    {
        return sprintf('%d %s', $this->amountMinor, $this->currencyCode);
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
