<?php

declare(strict_types=1);

namespace Kontor\Mail\Infrastructure\Adapters;

use Kontor\Mail\Application\RawEmailParser;
use Kontor\Mail\Contracts\InboundMailAdapterInterface;
use Kontor\Mail\DTO\InboundMessage;

/**
 * The one built-in adapter proving the "inbound adapters" pipeline
 * end-to-end (the same "built once, adopted by whoever wants it next"
 * precedent every other registry in this monorepo follows — e.g.
 * `LogActionHandler`). Simulates the common real-world shape of inbound
 * mail: an external provider's inbound-parse webhook (Postmark,
 * SendGrid, or a plain `.forward`/procmail pipe) hands Kontor a raw
 * email, which `pushRaw()` queues and `fetch()` drains — no live network
 * listener needed for this to be genuinely useful. A raw email that
 * fails to parse (missing "From" header) is silently dropped rather than
 * failing the whole batch, the same "one bad entry doesn't stop the
 * rest" behavior used throughout this monorepo.
 */
final class RawEmailForwardAdapter implements InboundMailAdapterInterface
{
    /**
     * @var string[]
     */
    private array $queue = [];

    public function __construct(
        private readonly RawEmailParser $parser = new RawEmailParser(),
    ) {
    }

    public function key(): string
    {
        return 'forward';
    }

    public function pushRaw(string $rawEmail): void
    {
        $this->queue[] = $rawEmail;
    }

    /**
     * @return InboundMessage[]
     */
    public function fetch(): array
    {
        $pending = $this->queue;
        $this->queue = [];

        $messages = [];

        foreach ($pending as $raw) {
            try {
                $messages[] = $this->parser->parse($raw);
            } catch (\Throwable) {
                continue;
            }
        }

        return $messages;
    }
}
