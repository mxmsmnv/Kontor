<?php

declare(strict_types=1);

namespace Kontor\Core\Infrastructure\Installation;

use ProcessWire\ProcessWire;
use ProcessWire\Role;
use ProcessWire\WireException;

final class EmployeeRoleProvisioner
{
    public const NAME = 'kontor-employee';

    private const DEFAULTS = [
        'Kontor' => ['kontor-access'],
        'KontorContacts' => [
            'kontor-contacts-contact-view', 'kontor-contacts-contact-edit',
            'kontor-contacts-company-view', 'kontor-contacts-company-edit',
        ],
        'KontorCRM' => [
            'kontor-crm-lead-view', 'kontor-crm-lead-edit',
            'kontor-crm-deal-view', 'kontor-crm-deal-edit',
        ],
        'Workspace' => ['workspace-use'],
    ];

    public function __construct(private readonly ProcessWire $wire)
    {
    }

    public function create(): void
    {
        $roles = $this->wire->roles;
        if ($roles->get(self::NAME)->id) {
            return;
        }

        $role = $roles->add(self::NAME);
        foreach (self::DEFAULTS as $module => $names) {
            if ($this->wire->modules->isInstalled($module)) {
                $this->grant($role, $names);
            }
        }
        if (!$roles->save($role)) {
            throw new WireException('Unable to save the default Kontor employee role.');
        }
        $role->meta('kontorEmployeeDefaults', true);
    }

    public function addComponentDefaults(string $module): void
    {
        if ($module === 'Kontor' || !isset(self::DEFAULTS[$module])) {
            return;
        }

        $roles = $this->wire->roles;
        $role = $roles->get(self::NAME);
        if ($role instanceof Role && $role->meta('kontorEmployeeDefaults')) {
            if ($this->grant($role, self::DEFAULTS[$module]) && !$roles->save($role)) {
                throw new WireException('Unable to save Kontor employee permissions.');
            }
        }
    }

    private function grant(Role $role, array $names): bool
    {
        $changed = false;
        foreach ($names as $name) {
            $permission = $this->wire->permissions->get($name);
            if ($permission->id && !$role->hasPermission($permission)) {
                $role->addPermission($permission);
                $changed = true;
            }
        }

        return $changed;
    }
}
