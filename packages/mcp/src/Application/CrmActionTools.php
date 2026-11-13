<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PDO;
use ProcessWire\KontorCRM;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait CrmActionTools
{
    private function crmActionTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorCRM')) {
            return [];
        }

        return [
            $this->tool(
                'kontor_crm_lead_convert',
                'Convert a Kontor CRM lead',
                'Convert a qualified lead into a deal in the default pipeline after the user confirms the conversion.',
                $this->mcpCrmLeadConvert(...),
                'draft',
                [
                    'uid' => $this->uidSchema(),
                    'idempotency_key' => $this->idempotencySchema(),
                    'confirmation' => ['type' => 'string', 'const' => 'CONVERT_LEAD'],
                ],
                ['uid', 'idempotency_key', 'confirmation'],
            ),
            $this->tool(
                'kontor_crm_deal_transition',
                'Move or close a Kontor CRM deal',
                'Move an open deal to a stage in its current pipeline or close it as won or lost after the user confirms the transition.',
                $this->mcpCrmDealTransition(...),
                'draft',
                [
                    'uid' => $this->uidSchema(),
                    'action' => ['type' => 'string', 'enum' => ['move', 'won', 'lost']],
                    'idempotency_key' => $this->idempotencySchema(),
                    'confirmation' => ['type' => 'string', 'const' => 'TRANSITION_DEAL'],
                    'stage_uid' => $this->uidSchema(),
                    'lost_reason' => ['type' => 'string', 'maxLength' => 255],
                ],
                ['uid', 'action', 'idempotency_key', 'confirmation'],
            ),
        ];
    }

    public function mcpCrmLeadConvert(string $uid, string $idempotency_key, string $confirmation): array
    {
        $actor = $this->crmActionActor(['lead-edit', 'lead-convert', 'deal-create']);
        if ($confirmation !== 'CONVERT_LEAD') {
            throw new WireException('Confirm the lead conversion.');
        }

        return $this->runCrmAction(
            $actor,
            'lead',
            $uid,
            'converted',
            [],
            $idempotency_key,
            function (KontorCRM $crm, string $organizationUid) use ($uid, $actor): array {
                $lead = $crm->leadRepository()->require($uid);
                if ($lead->isConverted() && $lead->convertedDealUid !== null) {
                    $deal = $crm->dealRepository()->find($lead->convertedDealUid);
                    if ($deal === null || !hash_equals($organizationUid, $deal->organizationId)) {
                        throw new Wire404Exception('Converted deal was not found.');
                    }
                    return ['dealUid' => $lead->convertedDealUid, 'alreadyConverted' => true];
                }
                if ($lead->status !== 'qualified' || !$lead->isQualifiedForConversion()) {
                    throw new WireException('Qualify the lead and connect a contact or company before conversion.');
                }
                $pipeline = $crm->pipelineRepository()->defaultForEntityType($organizationUid, 'deal');
                if ($pipeline === null || $crm->stageRepository()->firstOpenStage($pipeline->uid->toString()) === null) {
                    throw new WireException('Configure a default deal pipeline with an open stage before conversion.');
                }

                $dealUid = $crm->crmService()->convertLead($uid, (string) $actor->id);
                $modules = $this->wire()->modules;
                if ($modules->isInstalled('KontorCRMIntake')) {
                    $modules->get('KontorCRMIntake')->service()->copyAnswers(
                        $organizationUid,
                        'lead',
                        $uid,
                        'deal',
                        $dealUid,
                    );
                }

                return ['dealUid' => $dealUid];
            },
        );
    }

    public function mcpCrmDealTransition(
        string $uid,
        string $action,
        string $idempotency_key,
        string $confirmation,
        ?string $stage_uid = null,
        ?string $lost_reason = null,
    ): array {
        $permission = match ($action) {
            'move' => 'deal-move',
            'won' => 'deal-close-won',
            'lost' => 'deal-close-lost',
            default => throw new WireException('Unknown deal transition.'),
        };
        $actor = $this->crmActionActor([$permission]);
        if ($confirmation !== 'TRANSITION_DEAL') {
            throw new WireException('Confirm the deal transition.');
        }
        if (($action === 'move') !== ($stage_uid !== null)
            || ($stage_uid !== null && !$this->crmActionValidUid($stage_uid))
            || ($lost_reason !== null && ($action !== 'lost' || mb_strlen($lost_reason) > 255))) {
            throw new WireException('A valid stage UID is required only for move. A reason is allowed only for lost.');
        }
        $reason = $lost_reason === null ? null : $this->wire()->sanitizer->text($lost_reason);

        return $this->runCrmAction(
            $actor,
            'deal',
            $uid,
            $action,
            ['stageUid' => $stage_uid, 'lostReason' => $reason],
            $idempotency_key,
            function (KontorCRM $crm) use ($uid, $actor, $action, $stage_uid, $reason): array {
                $deal = $crm->dealRepository()->require($uid);
                if (!$deal->isOpen()) {
                    throw new WireException('This deal is closed.');
                }
                $service = $crm->crmService();
                $actorId = (string) $actor->id;
                if ($action === 'move') {
                    $stage = $crm->stageRepository()->find($stage_uid);
                    if ($stage === null || !hash_equals($deal->pipelineUid, $stage->pipelineUid)) {
                        throw new Wire404Exception('Stage was not found in the deal pipeline.');
                    }
                    if ($stage->stateType !== 'open') {
                        throw new WireException('Use won or lost to close a deal.');
                    }
                    $service->moveDealToStage($uid, $stage_uid, $actorId);
                } elseif ($action === 'won') {
                    $service->closeDealWon($uid, $actorId);
                } else {
                    $service->closeDealLost($uid, $reason === '' ? null : $reason, $actorId);
                }

                $updated = $crm->dealRepository()->require($uid);
                return ['dealUid' => $uid, 'status' => $updated->status, 'stageUid' => $updated->stageUid];
            },
        );
    }

    private function crmActionActor(array $permissions): User
    {
        $actor = $this->mcpActor();
        if ($actor === null) {
            throw new WirePermissionException('An authenticated Kontor MCP user is required.');
        }

        foreach ($permissions as $permission) {
            if (!$actor->isSuperuser() && !$actor->hasPermission('kontor-crm-' . $permission)) {
                throw new WirePermissionException('The linked user cannot perform this CRM action.');
            }
        }
        return $actor;
    }

    private function crmActionValidUid(string $uid): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) === 1;
    }

    private function runCrmAction(
        User $actor,
        string $entityType,
        string $uid,
        string $action,
        array $arguments,
        string $key,
        callable $operation,
    ): array {
        if (!$this->crmActionValidUid($uid) || preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('A valid record UID and idempotency key are required.');
        }

        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorCRM')) {
            throw new Wire404Exception('Kontor CRM is unavailable.');
        }
        $crm = $modules->get('KontorCRM');
        $container = $modules->get('Kontor')->container();
        $organizations = $container->get(OrganizationRepository::class);
        $organizationUid = $organizations->defaultOrganization('US', 'en', 'USD')->uid->toString();
        $pdo = $container->get(PDO::class);
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            // Lock the record before the first idempotency read. This also serializes distinct keys for the same record.
            $record = $entityType === 'lead'
                ? $crm->leadRepository()->find($uid, forUpdate: true)
                : $crm->dealRepository()->find($uid, forUpdate: true);
            if ($record === null || !hash_equals($organizationUid, $record->organizationId)) {
                throw new Wire404Exception('CRM record was not found.');
            }

            $response = $idempotency->remember(
                $organizationUid,
                'mcp:crm-action:' . $actor->id . ':' . hash('sha256', $key),
                ['entity' => $entityType, 'uid' => $uid, 'action' => $action, 'arguments' => $arguments],
                function () use ($operation, $crm, $organizationUid, $organizations, $container, $entityType, $uid, $action, $actor): array {
                    $body = $operation($crm, $organizationUid);
                    if (!($body['alreadyConverted'] ?? false)) {
                        $container->get(AuditLogger::class)->record(
                            $organizations->internalIdOf($organizationUid),
                            'crm',
                            $entityType,
                            $uid,
                            $action,
                            'user',
                            (string) $actor->id,
                            metadata: ['transport' => 'mcp'] + $body,
                        );
                    }
                    return ['status' => 200, 'body' => $body];
                },
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $response->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof WireException) {
                throw $error;
            }
            return $this->safe(static fn () => throw $error);
        }
    }
}
