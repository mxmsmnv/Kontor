<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Unit\Application;

use Kontor\Collaboration\Application\MentionParser;
use PHPUnit\Framework\TestCase;

final class MentionParserTest extends TestCase
{
    public function test_extracts_a_single_mention(): void
    {
        $this->assertSame([42], (new MentionParser())->extract('Hey @42, can you take a look?'));
    }

    public function test_extracts_multiple_unique_mentions_in_order(): void
    {
        $this->assertSame([1, 2], (new MentionParser())->extract('cc @1 and @2, also @1 again'));
    }

    public function test_returns_empty_array_when_no_mentions(): void
    {
        $this->assertSame([], (new MentionParser())->extract('No mentions here.'));
    }

    public function test_ignores_non_numeric_at_tokens(): void
    {
        $this->assertSame([], (new MentionParser())->extract('email me at @example.com'));
    }
}
