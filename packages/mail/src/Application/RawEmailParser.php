<?php

declare(strict_types=1);

namespace Kontor\Mail\Application;

use Kontor\Mail\DTO\InboundMessage;
use RuntimeException;

/**
 * A small, hand-rolled parser for a plain-text RFC822-ish email (headers,
 * a blank line, then body) — no third-party MIME library, the same
 * "avoid a heavy dependency for a narrow need" call `kontor/documents`
 * already made for its own template engine. Deliberately doesn't handle
 * MIME multipart, base64/quoted-printable encoding, or attachments —
 * `RawEmailForwardAdapter`'s own doc comment documents this as this
 * substage's own scope limit.
 */
final class RawEmailParser
{
    public function parse(string $raw): InboundMessage
    {
        $normalized = str_replace("\r\n", "\n", $raw);
        [$headerBlock, $body] = array_pad(explode("\n\n", $normalized, 2), 2, '');

        $headers = $this->parseHeaders($headerBlock);

        if (!isset($headers['from'])) {
            throw new RuntimeException('Raw email is missing a "From" header.');
        }

        $receivedAt = isset($headers['date'])
            ? new \DateTimeImmutable($headers['date'])
            : new \DateTimeImmutable();

        return new InboundMessage(
            fromAddress: $headers['from'],
            toAddresses: $this->splitAddresses($headers['to'] ?? ''),
            ccAddresses: $this->splitAddresses($headers['cc'] ?? ''),
            subject: $headers['subject'] ?? '(no subject)',
            bodyText: trim($body),
            receivedAt: $receivedAt,
        );
    }

    /**
     * @return array<string, string> lower-cased header name => value
     */
    private function parseHeaders(string $headerBlock): array
    {
        $headers = [];
        $currentName = null;

        foreach (explode("\n", $headerBlock) as $line) {
            if ($line === '') {
                continue;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && $currentName !== null) {
                $headers[$currentName] .= ' '.trim($line);

                continue;
            }

            $colonPosition = strpos($line, ':');

            if ($colonPosition === false) {
                continue;
            }

            $name = strtolower(trim(substr($line, 0, $colonPosition)));
            $headers[$name] = trim(substr($line, $colonPosition + 1));
            $currentName = $name;
        }

        return $headers;
    }

    /**
     * @return string[]
     */
    private function splitAddresses(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', $value)),
            static fn (string $address) => $address !== '',
        ));
    }
}
