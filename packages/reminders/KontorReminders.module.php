<?php

declare(strict_types=1);

namespace ProcessWire;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Reminders\Application\ReminderInbox;
use Kontor\Reminders\Application\ContactReminderDates;
use Kontor\Core\Infrastructure\Persistence\ExtensionRepository;

require_once __DIR__ . '/src/Application/ReminderInbox.php';
require_once __DIR__ . '/src/Application/ContactReminderDates.php';

class KontorReminders extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Reminders',
            'summary' => 'Review task and contact reminders and their delivery channels.',
            'version' => 100,
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorTasks'],
        ];
    }

    public function init(): void
    {
        if ($this->wire()->modules->isInstalled('Workspace')) {
            $this->addHookAfter('Workspace::getAppDefinitions', $this, 'registerWorkspaceSection');
        }
    }

    public function registerWorkspaceSection(HookEvent $event): void
    {
        $apps = $event->return;
        foreach ($apps as &$app) {
            if (($app['process'] ?? null) === 'ProcessKontor' && isset($app['allowedSections'])
                && !in_array('reminders', $app['allowedSections'], true)) {
                $app['allowedSections'][] = 'reminders';
            }
        }
        unset($app);
        $event->return = $apps;
    }

    public function contactDates(): ContactReminderDates
    {
        $container = $this->wire()->modules->get('Kontor')->container();
        return new ContactReminderDates($container->get(ExtensionRepository::class), $container->get(\PDO::class));
    }

    public function inboxFor(User $actor): array
    {
        $wire = $this->wire();
        if (!$actor->isLoggedin() || (int) $actor->id !== (int) $wire->user->id
            || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-access'))) {
            throw new WirePermissionException('Sign in to view reminders.');
        }

        $container = $wire->modules->get('Kontor')->container();
        $organization = $container->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();
        $tasks = $wire->modules->get('KontorTasks');

        $items = (new ReminderInbox($tasks->taskRepository(), $tasks->reminderRepository(), $tasks->relations()))
            ->list($organization, $tasks->accessFor($actor),
                $actor->isSuperuser() || $actor->hasPermission('kontor-contacts-contact-view'));
        if ($wire->modules->isInstalled('KontorContacts')
            && ($actor->isSuperuser() || $actor->hasPermission('kontor-contacts-contact-view'))) {
            $organizationId = $container->get(OrganizationRepository::class)->internalIdOf($organization);
            $items = array_merge($items, $this->contactDates()->list($organizationId, new \DateTimeImmutable('today')));
            usort($items, static fn (array $a, array $b): int => $a['remindAt'] <=> $b['remindAt']);
        }

        return $items;
    }

    public function channels(): array
    {
        return [
            ['id' => 'app', 'label' => 'In-app', 'description' => 'Review reminders in Kontor.'],
            ['id' => 'email', 'label' => 'Email', 'description' => 'Schedule an email to the assigned user.'],
        ];
    }

    public function ___install(): void
    {
        $registry = $this->wire()->modules->get('Kontor')->container()->get(ComponentRegistry::class);
        $registry->markInstalled('reminders', '1.0.0', 'reminders');
        $registry->enable('reminders');
    }

    public function ___uninstall(): void
    {
        $this->wire()->modules->get('Kontor')->container()->get(ComponentRegistry::class)->disable('reminders');
    }
}
