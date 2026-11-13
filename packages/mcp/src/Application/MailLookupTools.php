<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeInterface;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Mail\Domain\MailMessage;
use ProcessWire\KontorMail;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WirePermissionException;

trait MailLookupTools
{
    private function mcpMailLookupTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorMail')) {
            return [];
        }

        return [
            $this->tool('kontor_mail_list', 'Find visible mail', 'List visible mail matching subject or sender among the latest 500 active matches.',
                $this->mcpKontorMailList(...), 'read', [
                    'query' => ['type' => 'string', 'maxLength' => 120],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 499],
                ]),
            $this->tool('kontor_mail_get', 'Read a visible mail message', 'Read metadata and a bounded portion of one active message body.',
                $this->mcpKontorMailGet(...), 'read', [
                    'uid' => $this->uidSchema(),
                    'body_offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000000],
                ], ['uid']),
        ];
    }

    public function mcpKontorMailList(string $query = '', int $offset = 0): array
    {
        [$module, $actor, $organization] = $this->mcpMailReadContext();
        $items = [];
        $visible = 0;
        $repository = $module->messageRepository();
        for ($candidateOffset = 0; $candidateOffset < 500; $candidateOffset += 50) {
            $candidates = $repository->findActiveMatching($organization, trim($query), 50, $candidateOffset);
            foreach ($candidates as $message) {
                if (!$this->mcpMailTaskLinksVisible($module, $actor, $organization, $message) || $visible++ < $offset) {
                    continue;
                }
                $items[] = $this->mcpMailRecord($message);
                if (count($items) > 25) {
                    break 2;
                }
            }
            if (count($candidates) < 50) {
                break;
            }
        }

        return [
            'items' => array_slice($items, 0, 25),
            'next_offset' => count($items) > 25 ? $offset + 25 : null,
        ];
    }

    public function mcpKontorMailGet(string $uid, int $body_offset = 0): array
    {
        [$module, $actor, $organization] = $this->mcpMailReadContext();
        $message = $module->messageRepository()->findActive($organization, $uid);
        if ($message === null || !$this->mcpMailTaskLinksVisible($module, $actor, $organization, $message)) {
            throw new Wire404Exception('Mail message not found.');
        }

        return ['record' => $this->mcpMailRecord($message) + [
            'to' => array_slice($message->toAddresses, 0, 50),
            'cc' => array_slice($message->ccAddresses, 0, 50),
            'body_text' => mb_substr($message->bodyText, $body_offset, 8000),
            'next_body_offset' => mb_strlen($message->bodyText) > $body_offset + 8000 && $body_offset + 8000 <= 1000000
                ? $body_offset + 8000 : null,
        ]];
    }

    /** @return array{KontorMail, User, string} */
    private function mcpMailReadContext(): array
    {
        $actor = $this->mcpActor();
        $modules = $this->wire()->modules;
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-mail-message-view'))
            || !$modules->isInstalled('KontorMail')) {
            throw new WirePermissionException('The linked user cannot read organization mail.');
        }
        $organization = $modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();

        return [$modules->get('KontorMail'), $actor, $organization];
    }

    private function mcpMailTaskLinksVisible(KontorMail $module, User $actor, string $organization, MailMessage $message): bool
    {
        $modules = $this->wire()->modules;
        foreach ($module->entityLinking()->linkedEntities($organization, $message->uid->toString()) as $relation) {
            if ($relation['targetType'] !== 'task') {
                continue;
            }
            if (!$modules->isInstalled('KontorTasks')) {
                return false;
            }
            $tasks = $modules->get('KontorTasks');
            $task = $tasks->taskRepository()->find($relation['targetUid']);
            if ($task === null || !hash_equals($organization, $task->organizationId) || !$tasks->accessFor($actor)->allows($task)) {
                return false;
            }
        }

        return true;
    }

    private function mcpMailRecord(MailMessage $message): array
    {
        return [
            'uid' => $message->uid->toString(),
            'direction' => $message->direction,
            'from' => $message->fromAddress,
            'subject' => $message->subject,
            'status' => $message->status,
            'occurred_at' => $message->occurredAt->format(DateTimeInterface::ATOM),
        ];
    }
}
