<?php

declare(strict_types=1);

namespace Kontor\SDK\ValueObjects;

use InvalidArgumentException;

/**
 * ULID-based stable public identifier (kontor.md#10.3).
 * 26 characters, Crockford base32, lexicographically sortable by time.
 */
final class Uid implements \Stringable
{
    private const ENCODING = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    private const LENGTH = 26;

    private function __construct(private readonly string $value)
    {
    }

    public static function generate(?\DateTimeImmutable $at = null): self
    {
        $at ??= new \DateTimeImmutable();
        $ms = (int) ((float) $at->format('U.u') * 1000);

        $time = '';
        for ($i = 9; $i >= 0; $i--) {
            $time = self::ENCODING[$ms % 32] . $time;
            $ms = intdiv($ms, 32);
        }

        $random = '';
        for ($i = 0; $i < 16; $i++) {
            $random .= self::ENCODING[random_int(0, 31)];
        }

        return new self($time . $random);
    }

    public static function fromString(string $value): self
    {
        $value = strtoupper($value);

        if (!self::isValid($value)) {
            throw new InvalidArgumentException("\"{$value}\" is not a valid Kontor uid.");
        }

        return new self($value);
    }

    public static function isValid(string $value): bool
    {
        if (strlen($value) !== self::LENGTH) {
            return false;
        }

        return (bool) preg_match('/^[' . self::ENCODING . ']+$/', strtoupper($value));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
