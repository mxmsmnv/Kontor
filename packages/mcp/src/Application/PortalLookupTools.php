<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PDO;
use Kontor\Portal\Infrastructure\Persistence\PortalAccountRepository;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait PortalLookupTools
{
    private function mcpPortalLookupTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorPortal')) {
            return [];
        }

        return [
            $this->tool('kontor_portal_accounts_list', 'List customer portal accounts',
                'List organization portal account status and contact metadata, optionally for one contact.',
                $this->mcpKontorPortalAccountsList(...), 'read', [
                    'contact_uid' => $this->uidSchema(),
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ]),
            $this->tool('kontor_portal_account_get', 'Get a customer portal account',
                'Read organization portal account status and contact metadata.',
                $this->mcpKontorPortalAccountGet(...), 'read', ['uid' => $this->uidSchema()], ['uid']),
            $this->tool('kontor_portal_account_status', 'Enable or disable a customer portal account',
                'Apply a reviewed access change to a single portal account. Check its current status before confirming.',
                $this->mcpKontorPortalAccountStatus(...), 'draft', [
                    'uid' => $this->uidSchema(),
                    'action' => ['type' => 'string', 'enum' => ['enable', 'disable']],
                    'confirmation' => ['type' => 'string', 'const' => 'CHANGE_REVIEWED_PORTAL_ACCOUNT'],
                ], ['uid', 'action', 'confirmation'], destructive: true),
        ];
    }

    public function mcpKontorPortalAccountsList(?string $contact_uid = null, int $offset = 0): array
    {
        [$repository, $organization] = $this->mcpPortalReadContext();
        $rows = $repository->accountMetadata($organization, contactUid: $contact_uid, offset: $offset);

        return [
            'items' => array_slice($rows, 0, 25),
            'next_offset' => count($rows) > 25 && $offset + 25 <= 10000 ? $offset + 25 : null,
        ];
    }

    public function mcpKontorPortalAccountGet(string $uid): array
    {
        [$repository, $organization] = $this->mcpPortalReadContext();
        $record = $repository->accountMetadata($organization, uid: $uid, limit: 1)[0] ?? null;
        if ($record === null) {
            throw new Wire404Exception('Portal account not found.');
        }

        return ['record' => $record];
    }

    public function mcpKontorPortalAccountStatus(string $uid, string $action, string $confirmation): array
    {
        [$repository, $organization, $actor] = $this->mcpPortalReadContext();
        if ($confirmation !== 'CHANGE_REVIEWED_PORTAL_ACCOUNT' || !in_array($action, ['enable', 'disable'], true)) {
            throw new WireException('Confirm the reviewed portal account action.');
        }
        $status = $action === 'enable' ? 'active' : 'disabled';
        $container = $this->wire()->modules->get('Kontor')->container();
        $database = $container->get(PDO::class);
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($database);
        try {
            $account = $repository->accountMetadata($organization, uid: $uid, limit: 1)[0] ?? null;
            if ($account === null) {
                throw new Wire404Exception('Portal account not found.');
            }
            if ($account['status'] !== $status) {
                if (!$repository->changeActiveStatus($organization, $uid, $account['status'], $status)) {
                    throw new WireException('The portal account changed. Review its status before retrying.');
                }
                $organizationId = $container->get(OrganizationRepository::class)->internalIdOf($organization);
                $container->get(AuditLogger::class)->record(
                    $organizationId, 'portal', 'account', $uid, $action . 'd', 'user', (string) $actor->id,
                    metadata: ['transport' => 'mcp'],
                );
            }
            if ($ownsTransaction) {
                $database->commit();
            }

            return ['uid' => $uid, 'status' => $status];
        } catch (\Throwable $error) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            throw $error;
        }
    }

    /** @return array{PortalAccountRepository, string, \ProcessWire\User} */
    private function mcpPortalReadContext(): array
    {
        $actor = $this->mcpActor();
        $modules = $this->wire()->modules;
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-portal-account-manage'))
            || !$modules->isInstalled('KontorPortal')) {
            throw new WirePermissionException('The linked user cannot manage customer portal accounts.');
        }
        $organization = $modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();

        return [$modules->get('KontorPortal')->accountRepository(), $organization, $actor];
    }
}
