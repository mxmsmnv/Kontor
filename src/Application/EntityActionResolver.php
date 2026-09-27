<?php

declare(strict_types=1);

namespace Kontor\Core\Application;

/**
 * Builds cross-component actions from one central capability map.
 *
 * The resolver has no ProcessWire dependency, which keeps optional-component
 * behavior deterministic and testable. The Process module supplies only the
 * capabilities the current installation and user actually have.
 */
final class EntityActionResolver
{
    /**
     * @param array<string, bool> $capabilities
     * @return array<int, array{label: string, icon: string, route: string, primary: bool}>
     */
    public function resolve(string $entityType, string $entityUid, array $capabilities): array
    {
        if (!in_array($entityType, ['contact', 'company'], true) || trim($entityUid) === '') {
            return [];
        }

        $parameter = $entityType === 'contact' ? 'contact' : 'company';
        $uid = rawurlencode($entityUid);
        $definitions = [
            [
                'capability' => 'crm.lead.create',
                'label' => 'New lead',
                'icon' => 'user-plus',
                'route' => 'crm-lead/?' . $parameter . '=' . $uid,
                'primary' => true,
            ],
            [
                'capability' => 'crm.deal.create',
                'label' => 'New deal',
                'icon' => 'handshake-o',
                'route' => 'crm-deal/?' . $parameter . '=' . $uid,
                'primary' => false,
            ],
            [
                'capability' => 'tasks.task.create',
                'label' => 'New linked task',
                'icon' => 'check-square-o',
                'route' => 'task/?entity_type=' . $entityType . '&entity_uid=' . $uid,
                'primary' => false,
            ],
        ];

        return array_values(array_map(
            static fn (array $definition): array => [
                'label' => $definition['label'],
                'icon' => $definition['icon'],
                'route' => $definition['route'],
                'primary' => $definition['primary'],
            ],
            array_filter(
                $definitions,
                static fn (array $definition): bool => !empty($capabilities[$definition['capability']]),
            ),
        ));
    }
}
