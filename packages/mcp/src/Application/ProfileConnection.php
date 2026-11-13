<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use ProcessWire\HookEvent;
use ProcessWire\User;
use ProcessWire\WirePermissionException;

trait ProfileConnection
{
    private function profileClientOwner(string $id): int
    {
        return (int) $this->wire()->cache->getFor('KontorMCP', 'profile-client-' . $id);
    }

    private function profileAccountAllowed(User $user): bool
    {
        return $user->id && !$user->isGuest() && !$user->isUnpublished() && !$user->isTrash()
            && !$user->isLocked() && ($user->isSuperuser() || $user->hasPermission('kontor-access'));
    }

    public function addWorkspaceMcpMenu(): void
    {
        $wire = $this->wire();
        if (!$wire->adminTheme instanceof \ProcessWire\AdminThemeWorkspace
            || !$this->profileAccountAllowed($wire->user) || !$this->profileGatewayAvailable()) {
            return;
        }
        $url = $wire->modules->get('Workspace')->homeUrl() . 'account/#kontor-mcp';
        $asset = $wire->config->urls->siteModules . 'KontorMCP/workspace-connection.js';
        $wire->adminTheme->addExtraMarkup('body', '<script defer src="'
            . htmlspecialchars($asset, ENT_QUOTES, 'UTF-8') . '" data-mcp-account-url="'
            . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></script>');
    }

    private function recordProfileConnectionCheck(): void
    {
        $wire = $this->wire();
        $context = $wire->modules->get('McpServer')->requestClientContext();
        if ($context !== null && $this->profileClientOwner($context['id']) > 0) {
            $wire->cache->saveFor('KontorMCP', 'profile-check-' . $context['id'], (string) time(), 30 * 86400);
        }
    }

    public function renderWorkspaceConnectionLink(HookEvent $event): void
    {
        $wire = $this->wire();
        if (!$this->profileAccountAllowed($wire->user) || !$this->profileGatewayAvailable()) {
            return;
        }

        $url = $wire->modules->get('Workspace')->homeUrl() . 'account/#kontor-mcp';
        $event->return = '<p><a class="uk-button uk-button-default" href="'
            . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">MCP connections</a></p>' . (string) $event->return;
    }

    private function profileGatewayAvailable(): bool
    {
        $gateway = $this->wire()->modules->get('McpServer');

        return $gateway->enabled && parse_url($gateway->endpointUrl(), PHP_URL_SCHEME) === 'https';
    }

    public function renderProfileConnection(HookEvent $event): void
    {
        $wire = $this->wire();
        $actor = $wire->user;
        if (!$this->profileAccountAllowed($actor) || $wire->config->ajax) {
            return;
        }

        if (!$this->profileGatewayAvailable()) {
            return;
        }
        $gateway = $wire->modules->get('McpServer');
        header('Cache-Control: private, no-store');
        $issued = null;
        $input = $wire->input;
        $action = (string) $input->post('kontor_mcp_action');
        if ($input->requestMethod('POST') && $action !== '') {
            $wire->session->CSRF->validate('kontor-mcp-profile');
            if ($action === 'create') {
                $scope = (string) $input->post('kontor_mcp_scope');
                if (!in_array($scope, ['read', 'draft'], true)) {
                    throw new WirePermissionException('Select a personal token scope.');
                }
                $label = trim($wire->sanitizer->text((string) $input->post('kontor_mcp_label'), ['maxLength' => 60]));
                if ($label === '') {
                    throw new WirePermissionException('Enter a device label.');
                }
                $issued = $gateway->issueClient($label, [$scope], time() + 30 * 86400);
                try {
                    if (!$wire->cache->saveFor('KontorMCP', 'profile-client-' . $issued['id'], (string) $actor->id, 30 * 86400)) {
                        throw new \RuntimeException('Could not bind the token to your account.');
                    }
                } catch (\Throwable $error) {
                    $gateway->revokeClient($issued['id']);
                    throw $error;
                }
            } elseif ($action === 'revoke') {
                $id = (string) $input->post('kontor_mcp_client');
                if (!preg_match('/^[a-f0-9]{16}$/D', $id) || $this->profileClientOwner($id) !== (int) $actor->id) {
                    throw new WirePermissionException('This token does not belong to your account.');
                }
                if (!$gateway->revokeClient($id)) {
                    throw new \RuntimeException('Could not revoke the token.');
                }
                $wire->session->redirect('./#kontor-mcp');
                return;
            } else {
                throw new WirePermissionException('Unknown MCP action.');
            }
        }
        $clients = array_filter($gateway->clients(), fn (array $client): bool =>
            $client['enabled'] && $client['expires_at'] > time()
            && $this->profileClientOwner($client['id']) === (int) $actor->id);
        foreach ($clients as &$client) {
            $client['verified_at'] = (int) $wire->cache->getFor('KontorMCP', 'profile-check-' . $client['id']);
        }
        unset($client);
        $token = $wire->session->CSRF->getToken('kontor-mcp-profile');
        $url = $gateway->endpointUrl();
        $escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $asset = $wire->config->urls->siteModules . 'KontorMCP/profile-connection.js';
        ob_start();
        require dirname(__DIR__, 2) . '/profile-connection.php';
        $event->return = '<p><a href="#kontor-mcp">Manage MCP connections</a></p>'
            . (string) $event->return . ob_get_clean();
    }
}
