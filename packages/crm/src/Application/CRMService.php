<?php

declare(strict_types=1);

namespace Kontor\CRM\Application;

use Kontor\CRM\Contracts\CRMServiceInterface;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Persistence\StageRepository;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;
use RuntimeException;

/**
 * The "crm" capability's implementation (kontor.md#22.1's canonical
 * manifest example names Kontor\CRM\Contracts\CRMServiceInterface as the
 * contract; this is what gets registered against it).
 */
final class CRMService implements CRMServiceInterface
{
    public function __construct(
        private readonly LeadConversionService $conversion,
        private readonly DealRepository $deals,
        private readonly StageRepository $stages,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    public function convertLead(string $leadUid, ?string $actorId = null): string
    {
        $deal = $this->conversion->convert($leadUid);

        $this->emit('crm.lead.converted', $deal->organizationId, 'lead', $leadUid, $actorId, ['dealUid' => $deal->uid->toString()]);
        $this->emit('crm.deal.stage_changed', $deal->organizationId, 'deal', $deal->uid->toString(), $actorId, ['stageUid' => $deal->stageUid]);

        return $deal->uid->toString();
    }

    public function moveDealToStage(string $dealUid, string $stageUid, ?string $actorId = null): void
    {
        $deal = $this->deals->require($dealUid);

        if (!$deal->isOpen()) {
            throw new RuntimeException("Deal \"{$dealUid}\" is not open and cannot be moved between stages.");
        }

        $stage = $this->stages->require($stageUid);

        if (!hash_equals($deal->pipelineUid, $stage->pipelineUid)) {
            throw new RuntimeException(
                "Stage \"{$stageUid}\" does not belong to deal \"{$dealUid}\" pipeline."
            );
        }

        $deal->stageUid = $stageUid;
        $this->deals->save($deal);

        $this->emit('crm.deal.stage_changed', $deal->organizationId, 'deal', $dealUid, $actorId, ['stageUid' => $stageUid]);
    }

    public function closeDealWon(string $dealUid, ?string $actorId = null): void
    {
        $deal = $this->deals->require($dealUid);

        if (!$deal->isOpen()) {
            throw new RuntimeException("Deal \"{$dealUid}\" is already closed.");
        }

        $wonStage = $this->stages->firstStageOfType($deal->pipelineUid, 'won');

        if ($wonStage !== null) {
            $deal->stageUid = $wonStage->uid->toString();
        }

        $deal->status = 'won';
        $deal->wonAt = new \DateTimeImmutable();
        $this->deals->save($deal);

        $this->emit('crm.deal.won', $deal->organizationId, 'deal', $dealUid, $actorId);
    }

    public function closeDealLost(string $dealUid, ?string $reason = null, ?string $actorId = null): void
    {
        $deal = $this->deals->require($dealUid);

        if (!$deal->isOpen()) {
            throw new RuntimeException("Deal \"{$dealUid}\" is already closed.");
        }

        $lostStage = $this->stages->firstStageOfType($deal->pipelineUid, 'lost');

        if ($lostStage !== null) {
            $deal->stageUid = $lostStage->uid->toString();
        }

        $deal->status = 'lost';
        $deal->lostAt = new \DateTimeImmutable();
        $deal->lostReason = $reason;
        $this->deals->save($deal);

        $this->emit('crm.deal.lost', $deal->organizationId, 'deal', $dealUid, $actorId, $reason !== null ? ['reason' => $reason] : []);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emit(string $eventName, string $organizationId, string $entityType, string $entityId, ?string $actorId, array $data = []): void
    {
        $this->events?->dispatch(KontorEvent::create(
            event: $eventName,
            organizationId: $organizationId,
            entityType: $entityType,
            entityId: $entityId,
            actorType: $actorId !== null ? 'user' : 'system',
            actorId: $actorId,
            data: $data,
        ));
    }
}
