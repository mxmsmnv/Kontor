<?php

declare(strict_types=1);

namespace Kontor\SDK\Tests\ValueObjects;

use InvalidArgumentException;
use Kontor\SDK\ValueObjects\Uid;
use PHPUnit\Framework\TestCase;

final class UidTest extends TestCase
{
    public function test_generate_produces_26_char_valid_uid(): void
    {
        $uid = Uid::generate();

        $this->assertSame(26, strlen($uid->toString()));
        $this->assertTrue(Uid::isValid($uid->toString()));
    }

    public function test_generated_uids_are_lexicographically_sortable_by_time(): void
    {
        $earlier = Uid::generate(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $later = Uid::generate(new \DateTimeImmutable('2026-01-01T00:00:01Z'));

        $this->assertLessThan(0, strcmp($earlier->toString(), $later->toString()));
    }

    public function test_from_string_rejects_invalid_uid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Uid::fromString('not-a-valid-uid');
    }

    public function test_equals(): void
    {
        $value = Uid::generate()->toString();

        $this->assertTrue(Uid::fromString($value)->equals(Uid::fromString($value)));
    }
}
