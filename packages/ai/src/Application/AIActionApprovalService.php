<?php

declare(strict_types=1);

namespace Kontor\AI\Application;

use Kontor\AI\Domain\PendingAIAction;
use Kontor\AI\DTO\AIActionResult;
use Kontor\AI\Infrastructure\Persistence\PendingAIActionRepository;
use Kontor\SDK\DTO\AIRequest;

/**
 * The "approval workflow" milestone (kontor.md#32: "Critical AI actions
 * require confirmation unless an explicit approved automation policy
 * allows them"). `requestAndMaybeApprove()` runs the request through
 * `AIGateway` as usual, but when the resulting response requires
 * confirmation, its output is withheld from the caller and stashed as a
 * `PendingAIAction` instead — the caller gets the pending record back,
 * not the raw output, until a human calls `approve()`.
 */
final class AIActionApprovalService
{
    public function __construct(
        private readonly AIGateway $gateway,
        private readonly PendingAIActionRepository $pending,
    ) {
    }

    public function requestAndMaybeApprove(AIRequest $request, ?int $requestedBy = null): AIActionResult
    {
        $response = $this->gateway->execute($request);

        if (!$response->requiresConfirmation) {
            return AIActionResult::completed($response);
        }

        $pendingAction = PendingAIAction::create($request->organizationId, $request->capability, $request->input, $response->output, $requestedBy);
        $this->pending->save($pendingAction);

        return AIActionResult::pending($pendingAction);
    }

    public function approve(string $pendingActionUid, ?int $decidedBy = null): PendingAIAction
    {
        $action = $this->pending->require($pendingActionUid);
        $action->approve($decidedBy);
        $this->pending->save($action);

        return $action;
    }

    public function reject(string $pendingActionUid, ?int $decidedBy = null): PendingAIAction
    {
        $action = $this->pending->require($pendingActionUid);
        $action->reject($decidedBy);
        $this->pending->save($action);

        return $action;
    }
}
