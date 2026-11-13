<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Portal\Infrastructure\Persistence\PortalAccountRepository;
use ProcessWire\Wire404Exception;
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

    /** @return array{PortalAccountRepository, string} */
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

        return [$modules->get('KontorPortal')->accountRepository(), $organization];
    }
}
