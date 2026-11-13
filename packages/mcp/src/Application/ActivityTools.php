<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Core\Domain\AuditEvent;
use Kontor\Core\Infrastructure\Persistence\AuditEventRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use ProcessWire\WirePermissionException;

trait ActivityTools
{
    private function activityTools(): array
    {
        return [
            $this->tool(
                'kontor_activity_list',
                'Read Kontor activity',
                'List recent audit summaries. Task activity requires permission to view all tasks.',
                $this->mcpActivityList(...),
                'read',
                [
                    'component' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_-]{0,63}$'],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ],
            ),
        ];
    }

    public function mcpActivityList(?string $component = null, int $offset = 0): array
    {
        $actor = $this->mcpActor();
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-audit-view'))) {
            throw new WirePermissionException('The linked user cannot view Kontor activity.');
        }
        $container = $this->wire()->modules->get('Kontor')->container();
        $organizations = $container->get(OrganizationRepository::class);
        $uid = $organizations->defaultOrganization('US', 'en', 'USD')->uid->toString();
        $includeTasks = $actor->isSuperuser() || ($actor->hasPermission('kontor-tasks-task-view')
            && $actor->hasPermission('kontor-tasks-task-view-all'));
        $events = $container->get(AuditEventRepository::class)->findRecent(
            $organizations->internalIdOf($uid), limit: 26, component: $component,
            offset: $offset, includeTaskActivity: $includeTasks,
        );

        return [
            'items' => array_map(static fn (AuditEvent $event): array => [
                'uid' => $event->uid,
                'component' => $event->component,
                'entity_type' => $event->entityType,
                'action' => $event->action,
                'actor_type' => $event->actorType,
                'occurred_at' => $event->occurredAt->format(DATE_ATOM),
            ], array_slice($events, 0, 25)),
            'next_offset' => count($events) > 25 && $offset + 25 <= 10000 ? $offset + 25 : null,
        ];
    }
}
