<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Unit\Application;

use Kontor\Mail\Application\RawEmailParser;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RawEmailParserTest extends TestCase
{
    private RawEmailParser $parser;

    protected function setUp(): void
    {
        $this->parser = new RawEmailParser();
    }

    public function test_parses_headers_and_body(): void
    {
        $raw = "From: alice@example.com\nTo: support@example.com\nSubject: Hello\n\nBody text here.";

        $message = $this->parser->parse($raw);

        $this->assertSame('alice@example.com', $message->fromAddress);
        $this->assertSame(['support@example.com'], $message->toAddresses);
        $this->assertSame('Hello', $message->subject);
        $this->assertSame('Body text here.', $message->bodyText);
    }

    public function test_splits_multiple_to_and_cc_addresses(): void
    {
        $raw = "From: alice@example.com\nTo: a@example.com, b@example.com\nCc: c@example.com\nSubject: Hi\n\nBody";

        $message = $this->parser->parse($raw);

        $this->assertSame(['a@example.com', 'b@example.com'], $message->toAddresses);
        $this->assertSame(['c@example.com'], $message->ccAddresses);
    }

    public function test_missing_to_or_cc_defaults_to_empty_array(): void
    {
        $raw = "From: alice@example.com\nSubject: Hi\n\nBody";

        $message = $this->parser->parse($raw);

        $this->assertSame([], $message->toAddresses);
        $this->assertSame([], $message->ccAddresses);
    }

    public function test_missing_subject_defaults_to_no_subject(): void
    {
        $raw = "From: alice@example.com\n\nBody";

        $message = $this->parser->parse($raw);

        $this->assertSame('(no subject)', $message->subject);
    }

    public function test_uses_the_date_header_when_present(): void
    {
        $raw = "From: alice@example.com\nDate: 2026-01-15T10:00:00+00:00\n\nBody";

        $message = $this->parser->parse($raw);

        $this->assertSame('2026-01-15T10:00:00+00:00', $message->receivedAt->format(DATE_ATOM));
    }

    public function test_a_folded_header_continuation_is_joined(): void
    {
        $raw = "From: alice@example.com\nSubject: This is a long\n subject line\n\nBody";

        $message = $this->parser->parse($raw);

        $this->assertSame('This is a long subject line', $message->subject);
    }

    public function test_missing_from_header_is_a_parse_error(): void
    {
        $raw = "Subject: Hi\n\nBody";

        $this->expectException(RuntimeException::class);

        $this->parser->parse($raw);
    }

    public function test_handles_crlf_line_endings(): void
    {
        $raw = "From: alice@example.com\r\nTo: bob@example.com\r\nSubject: Hi\r\n\r\nBody text.";

        $message = $this->parser->parse($raw);

        $this->assertSame(['bob@example.com'], $message->toAddresses);
        $this->assertSame('Body text.', $message->bodyText);
    }
}
