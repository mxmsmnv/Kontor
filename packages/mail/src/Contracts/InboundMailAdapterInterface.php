<?php

declare(strict_types=1);

namespace Kontor\Mail\Contracts;

use Kontor\Mail\DTO\InboundMessage;

/**
 * The "inbound adapters" milestone's extension point. Not an SDK
 * contract (kontor.md section 9 has no mail-adapter entry) — same status
 * as `kontor/automation`'s `ActionHandlerInterface`. A registered adapter
 * decides for itself how messages arrive (a forwarding webhook, IMAP
 * polling, …); `InboundMailService::poll()` just calls `fetch()` and
 * persists whatever comes back.
 */
interface InboundMailAdapterInterface
{
    public function key(): string;

    /**
     * @return InboundMessage[] every message fetched since the last call
     */
    public function fetch(): array;
}
