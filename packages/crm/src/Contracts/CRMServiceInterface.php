<?php

declare(strict_types=1);

namespace Kontor\CRM\Contracts;

/**
 * kontor.md#22.1's canonical manifest example names this contract exactly
 * ("Kontor\\CRM\\Contracts\\CRMServiceInterface") but doesn't show its
 * methods — like JobInterface and CacheInterface before it, the shape is
 * this component's own design. Other components (e.g. a future
 * kontor/sales creating a quotation from a won deal) consume CRM through
 * this facade rather than depending on CRM's internal classes, the same
 * way every other capability works.
 */
interface CRMServiceInterface
{
    /**
     * kontor.md diagram 17.1: converts a qualified lead into a deal in its
     * organization's default pipeline. Returns the new deal's uid.
     */
    public function convertLead(string $leadUid, ?string $actorId = null): string;

    public function moveDealToStage(string $dealUid, string $stageUid, ?string $actorId = null): void;

    public function closeDealWon(string $dealUid, ?string $actorId = null): void;

    public function closeDealLost(string $dealUid, ?string $reason = null, ?string $actorId = null): void;
}
