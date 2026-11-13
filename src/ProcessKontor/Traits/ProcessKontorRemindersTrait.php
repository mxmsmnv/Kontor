<?php

namespace ProcessWire;

trait ProcessKontorRemindersTrait
{
    public function ___executeReminders(): string
    {
        $this->requirePermission('kontor-tasks-task-view');
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorReminders')) {
            throw new Wire404Exception($this->_('Reminders is unavailable.'));
        }

        $module = $modules->get('KontorReminders');
        $channels = array_column($module->channels(), null, 'id');
        $selectedChannel = (string) $this->wire()->input->get('channel');
        if (!isset($channels[$selectedChannel])) {
            $selectedChannel = '';
        }
        $items = $module->inboxFor($this->wire()->user);
        if ($selectedChannel !== '') {
            $items = array_values(array_filter(
                $items,
                static fn (array $item): bool => $item['channel'] === $selectedChannel,
            ));
        }

        $this->setPageTitle($this->_('Kontor · Reminders'));

        return $this->renderTemplate('reminders', [
            'items' => $items,
            'channels' => $channels,
            'selectedChannel' => $selectedChannel,
            'canManageEmail' => $this->can('kontor-tasks-reminder-manage'),
        ]);
    }
}
