<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Registry\ComponentRegistry;

/**
 * The single public admin shell (kontor.md#6.3). Business components
 * register admin routes into this Process module via RouteRegistry rather
 * than shipping their own Process module.
 */
class ProcessKontor extends Process
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor',
            'summary' => 'Kontor ERP, CRM and business operations admin.',
            'version' => '002',
            'author' => 'Maxim Semenov',
            'icon' => 'cubes',
            'permission' => 'kontor-access',
            'page' => [
                'name' => 'kontor',
                'title' => 'Kontor',
            ],
            'useNavJSON' => true,
        ];
    }

    public function ___execute(): string
    {
        $components = $this->componentRegistry()->all();

        $out = '<h2>' . $this->_('Kontor') . '</h2>';
        $out .= '<p>' . sprintf(
            $this->_n('%d component registered.', '%d components registered.', count($components)),
            count($components)
        ) . '</p>';

        return $out;
    }

    public function ___executeComponents(): string
    {
        if (!$this->wire()->user->hasPermission('kontor-components-view')) {
            throw new WirePermissionException($this->_('You do not have permission to view Kontor components.'));
        }

        $table = $this->modules->get('MarkupAdminDataTable');
        $table->headerRow([$this->_('Component'), $this->_('Version'), $this->_('Status')]);

        foreach ($this->componentRegistry()->all() as $component) {
            $table->row([$component['name'], $component['version'], $component['status']]);
        }

        return $table->render();
    }

    private function componentRegistry(): ComponentRegistry
    {
        return $this->wire()->modules->get('Kontor')->container()->get(ComponentRegistry::class);
    }
}
