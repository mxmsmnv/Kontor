<?php

namespace ProcessWire;

use Kontor\Collaboration\Domain\Comment;
use Kontor\Collaboration\Domain\Note;
use Kontor\Catalog\Application\PriceListDuplicator;
use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Domain\Category;
use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\Catalog\Support\TaxCode;
use Kontor\Catalog\Support\UnitOfMeasure;
use Kontor\Contacts\Application\ContactDuplicateDetector;
use Kontor\Contacts\Application\TagService;
use Kontor\Contacts\Domain\Address;
use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Domain\ContactCompanyMembership;
use Kontor\Contacts\Infrastructure\Persistence\AddressRepository;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Infrastructure\Persistence\MembershipRepository;
use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;
use Kontor\Core\Application\AuditChangePresenter;
use Kontor\Core\Application\AuditCsvExporter;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Application\BackupManager;
use Kontor\Core\Application\BackupOverviewBuilder;
use Kontor\Core\Application\ComponentOverviewBuilder;
use Kontor\Core\Application\EntityActionResolver;
use Kontor\Core\Application\ExportManager;
use Kontor\Core\Application\HealthCheckRunner;
use Kontor\Core\Application\HealthOverviewBuilder;
use Kontor\Core\Application\ImportManager;
use Kontor\Core\Application\NavigationAvailability;
use Kontor\Core\Domain\ImportBatchResult;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Health\CoreHealthCheck;
use Kontor\Core\Infrastructure\Backup\BackupArchiveBuilder;
use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Core\Infrastructure\Persistence\AuditEventRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Application\LedgerExpensePostingService;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\Invoices\Application\LedgerInvoicePostingService;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Germany\DTO\LocalizedInvoiceInput;
use Kontor\Germany\DTO\LocalizedLineItemInput;
use Kontor\Germany\DTO\LocalizedPartyInput;
use Kontor\Ledger\Domain\Account;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Payments\Application\LedgerAllocationPostingService;
use Kontor\Payments\Domain\Payment;
use Kontor\Projects\Domain\BillableItem;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Domain\Supplier;
use Kontor\Queue\Infrastructure\Persistence\JobRepository;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\ExportContext;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\Events\KontorEvent;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Tasks\Domain\Task;
use Kontor\Workflow\Domain\ApprovalRequest;

/**
 * Administrative feature slice composed by ProcessKontor.
 *
 * @internal
 */
trait ProcessKontorWorkTrait
{

    public function ___executeTasks(): string
    {
        $this->requireTasks();
        $this->requirePermission('kontor-tasks-task-view');
        $archived = (string) $this->wire()->input->get('archived') === '1';
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['open', 'in_progress', 'done', 'cancelled']
        );
        $priority = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('priority'),
            ['low', 'normal', 'high', 'urgent']
        );
        $scope = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('scope'),
            ['active', 'all', 'mine', 'overdue', 'today', 'upcoming']
        ) ?? ($archived ? 'all' : 'active');
        if (in_array($status, ['done', 'cancelled'], true) && $scope !== 'all') {
            $scope = 'all';
        }
        $repository = $this->taskModule()->taskRepository();
        $allTasks = $repository->findMatching(
            $this->organizationUid(),
            archived: $archived,
            limit: 500,
        );
        $tasks = $repository->findMatching(
            $this->organizationUid(),
            $query,
            $status,
            $priority,
            $archived,
            limit: 250,
        );
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');
        $upcoming = $today->modify('+7 days');
        $currentUserId = (int) $this->wire()->user->id;
        $tasks = array_values(array_filter(
            $tasks,
            static fn (Task $task): bool => match ($scope) {
                'active' => $task->isOpen(),
                'mine' => $task->isOpen() && $task->assignedTo === $currentUserId,
                'overdue' => $task->isOverdue(),
                'today' => $task->isOpen() && $task->dueAt !== null
                    && $task->dueAt >= $today && $task->dueAt < $tomorrow,
                'upcoming' => $task->isOpen() && $task->dueAt !== null
                    && $task->dueAt >= $tomorrow && $task->dueAt < $upcoming,
                default => true,
            }
        ));
        $assigneeLabels = [];
        foreach ($tasks as $task) {
            if ($task->assignedTo === null || isset($assigneeLabels[$task->assignedTo])) {
                continue;
            }
            $assignee = $this->wire()->users->get($task->assignedTo);
            if ($assignee->id > 0) {
                $assigneeLabels[$task->assignedTo] = (string) (
                    $assignee->get('title') ?: ucfirst((string) $assignee->name)
                );
            }
        }
        $this->setPageTitle($archived
            ? $this->_('Kontor · Archived tasks')
            : $this->_('Kontor · Tasks'));

        return $this->renderTemplate('tasks', [
            'tasks' => $tasks,
            'allTasks' => $allTasks,
            'query' => $query,
            'selectedStatus' => $status,
            'selectedPriority' => $priority,
            'selectedScope' => $scope,
            'archived' => $archived,
            'assigneeLabels' => $assigneeLabels,
            'currentUserId' => $currentUserId,
            'canCreate' => $this->can('kontor-tasks-task-create'),
        ]);
    }

    public function ___executeTask(): string
    {
        $this->requireTasks();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $archived = (string) $this->wire()->input->get('archived') === '1';
        $repository = $this->taskModule()->taskRepository();
        $task = $id !== '' ? $repository->require($id) : null;
        $this->requirePermission($task === null ? 'kontor-tasks-task-create' : 'kontor-tasks-task-edit');

        if ($task !== null) {
            $this->requireSameOrganization($task->organizationId);
        }

        $contextType = '';
        $contextUid = '';
        $contextLabel = '';
        if ($task === null) {
            $contextType = $this->wire()->sanitizer->option(
                (string) ($this->wire()->input->post('context_entity_type')
                    ?: $this->wire()->input->get('entity_type')),
                ['contact', 'company'],
            ) ?? '';
            $contextUid = $this->wire()->sanitizer->text(
                (string) ($this->wire()->input->post('context_entity_uid')
                    ?: $this->wire()->input->get('entity_uid')),
            );
            if ($contextType !== '' && $contextUid !== '') {
                $contextLabel = $this->customerEntityLabel($contextType, $contextUid);
            } else {
                $contextType = '';
                $contextUid = '';
            }
        }

        $values = [
            'title' => $task?->title ?? ($contextLabel !== '' ? 'Follow up with ' . $contextLabel : ''),
            'description' => $task?->description ?? '',
            'priority' => $task?->priority ?? 'normal',
            'dueAt' => $task?->dueAt?->format('Y-m-d\TH:i') ?? '',
            'recurrenceRule' => $task?->recurrenceRule ?? '',
            'recurrenceUntil' => $task?->recurrenceUntil?->format('Y-m-d') ?? '',
            'assignedToMe' => $task === null
                || $task->assignedTo === (int) $this->wire()->user->id,
        ];
        $error = '';

        if ($this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'title' => trim($this->wire()->sanitizer->text((string) $this->wire()->input->post('title'))),
                'description' => trim((string) $this->wire()->input->post('description')),
                'priority' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('priority'),
                    ['low', 'normal', 'high', 'urgent']
                ) ?? 'normal',
                'dueAt' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('due_at')),
                'recurrenceRule' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('recurrence_rule'),
                    ['daily', 'weekly', 'monthly', 'yearly']
                ) ?? '',
                'recurrenceUntil' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('recurrence_until')
                ),
                'assignedToMe' => (string) $this->wire()->input->post('assigned_to_me') === '1',
            ];
            $dueAt = null;
            $recurrenceUntil = null;

            if ($values['title'] === '') {
                $error = $this->_('Task title is required.');
            }
            if ($error === '' && $values['dueAt'] !== '') {
                try {
                    $dueAt = new \DateTimeImmutable($values['dueAt']);
                } catch (\Throwable) {
                    $error = $this->_('Due date is invalid.');
                }
            }
            if ($error === '' && $values['recurrenceUntil'] !== '') {
                try {
                    $recurrenceUntil = new \DateTimeImmutable($values['recurrenceUntil']);
                } catch (\Throwable) {
                    $error = $this->_('Recurrence end date is invalid.');
                }
            }
            if ($error === '' && $values['recurrenceRule'] !== '' && $dueAt === null) {
                $error = $this->_('A recurring task needs a due date.');
            }

            if ($error === '') {
                if ($task === null && $contextType !== '' && $contextUid !== '') {
                    $this->requirePermission('kontor-tasks-relation-manage');
                }
                $task ??= Task::create($this->organizationUid(), $values['title']);
                $task->title = $values['title'];
                $task->description = $values['description'] !== '' ? $values['description'] : null;
                $task->priority = $values['priority'];
                $task->dueAt = $dueAt;
                $task->recurrenceRule = $values['recurrenceRule'] !== '' ? $values['recurrenceRule'] : null;
                $task->recurrenceUntil = $recurrenceUntil;
                $task->assignedTo = $values['assignedToMe'] ? (int) $this->wire()->user->id : null;
                $isNew = $id === '';
                $repository->save($task);
                if ($isNew && $contextType !== '' && $contextUid !== '') {
                    $this->taskModule()->relations()->linkToEntity(
                        $this->organizationUid(),
                        $task->uid->toString(),
                        $contextType,
                        $contextUid,
                    );
                }
                $this->audit(
                    'tasks',
                    'task',
                    $task->uid->toString(),
                    $isNew ? 'created' : 'updated',
                    current: [
                        'title' => $task->title,
                        'priority' => $task->priority,
                        'dueAt' => $task->dueAt?->format(DATE_ATOM),
                        'recurrenceRule' => $task->recurrenceRule,
                    ],
                );
                $this->message($isNew ? $this->_('Task created.') : $this->_('Task updated.'));
                $this->wire()->session->redirect(
                    '../task/?id=' . rawurlencode($task->uid->toString())
                );
            }
        }

        $this->setPageTitle($task === null
            ? $this->_('Kontor · New task')
            : sprintf($this->_('Kontor · %s'), $task->title));
        $collaborationReady = $task !== null && $this->collaborationReady();
        $reminders = $task !== null
            ? $this->taskModule()->reminderRepository()->forTask($task->uid->toString())
            : [];
        $notes = [];
        $comments = [];
        $following = false;
        $user = $this->wire()->user;
        $canViewNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-view');
        $canCreateNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-create');
        $canArchiveNotes = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-note-archive');
        $canViewComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-view');
        $canCreateComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-create');
        $canArchiveComments = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-comment-archive');
        $canManageFollow = $user->isSuperuser() || $user->hasPermission('kontor-collaboration-follow-manage');

        if ($collaborationReady) {
            $collaboration = $this->collaborationModule();
            $notes = $canViewNotes
                ? $collaboration->noteRepository()->forEntity('task', $task->uid->toString())
                : [];
            $comments = $canViewComments
                ? $collaboration->commentRepository()->forEntity('task', $task->uid->toString())
                : [];
            if ($canManageFollow) {
                $following = $collaboration->followerRepository()->isFollowing(
                    $this->organizationUid(),
                    'task',
                    $task->uid->toString(),
                    (int) $user->id,
                );
            }
            if ($canViewComments) {
                $collaboration->unreadStateService()->markRead(
                    $this->organizationUid(),
                    (int) $user->id,
                    'task',
                    $task->uid->toString(),
                );
            }
        }

        $assigneeLabel = $this->_('Unassigned');
        if ($task?->assignedTo !== null) {
            $assignee = $this->wire()->users->get($task->assignedTo);
            $assigneeLabel = $assignee->id > 0
                ? (string) ($assignee->get('title') ?: ucfirst((string) $assignee->name))
                : $this->_('Former user');
        }

        return $this->renderTemplate('task', [
            'task' => $task,
            'values' => $values,
            'error' => $error,
            'archived' => $archived,
            'reminders' => $reminders,
            'canManageReminders' => $user->isSuperuser()
                || $user->hasPermission('kontor-tasks-reminder-manage'),
            'collaborationReady' => $collaborationReady,
            'notes' => $notes,
            'comments' => $comments,
            'following' => $following,
            'canViewNotes' => $canViewNotes,
            'canCreateNotes' => $canCreateNotes,
            'canArchiveNotes' => $canArchiveNotes,
            'canViewComments' => $canViewComments,
            'canCreateComments' => $canCreateComments,
            'canArchiveComments' => $canArchiveComments,
            'canManageFollow' => $canManageFollow,
            'contextType' => $contextType,
            'contextUid' => $contextUid,
            'contextLabel' => $contextLabel,
            'relatedRecords' => $task !== null ? $this->taskRelatedRecords($task) : [],
            'authorLabels' => $this->collaborationAuthorLabels(array_merge($notes, $comments)),
            'assigneeLabel' => $assigneeLabel,
            'canComplete' => $this->can('kontor-tasks-task-complete'),
            'canCancel' => $this->can('kontor-tasks-task-cancel'),
            'canEdit' => $this->can('kontor-tasks-task-edit'),
        ]);
    }

    public function ___executeTaskAction(): void
    {
        $this->requirePost();
        $this->requireTasks();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['start', 'complete', 'cancel', 'archive', 'restore']
        );
        $this->requireAction($action, ['start', 'complete', 'cancel', 'archive', 'restore']);
        $this->requirePermission(match ($action) {
            'complete' => 'kontor-tasks-task-complete',
            'cancel' => 'kontor-tasks-task-cancel',
            default => 'kontor-tasks-task-edit',
        });
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $module = $this->taskModule();
        $task = $module->taskRepository()->require($id);
        $this->requireSameOrganization($task->organizationId);

        if ($action === 'start') {
            $module->workflow()->start($id);
        } elseif ($action === 'complete') {
            $result = $module->workflow()->complete($id);
            if ($result['next'] !== null) {
                $this->audit('tasks', 'task', $id, 'complete');
                $this->audit(
                    'tasks',
                    'task',
                    $result['next']->uid->toString(),
                    'recurrence_created',
                    current: ['sourceTaskUid' => $id, 'dueAt' => $result['next']->dueAt?->format(DATE_ATOM)],
                );
                $this->message($this->_('Task completed and the next occurrence was created.'));
                $this->wire()->session->redirect(
                    '../task/?id=' . rawurlencode($result['next']->uid->toString())
                );
            }
        } elseif ($action === 'cancel') {
            $module->workflow()->cancel($id);
        } elseif ($action === 'restore') {
            $module->taskRepository()->restore($id);
            $this->audit('tasks', 'task', $id, 'restored');
            $this->message($this->_('Task restored.'));
            $this->wire()->session->redirect('../tasks/');
        } else {
            $module->taskRepository()->archive($id);
            $this->audit('tasks', 'task', $id, 'archived');
            $this->message($this->_('Task archived.'));
            $this->wire()->session->redirect('../tasks/?archived=1');
        }

        $this->audit('tasks', 'task', $id, $action);
        $this->message($this->_('Task updated.'));
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($id));
    }

    public function ___executeTaskReminder(): void
    {
        $this->requirePost();
        $this->requireTasks();
        $this->requirePermission('kontor-tasks-reminder-manage');
        $taskUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('task_uid')
        );
        $task = $this->taskModule()->taskRepository()->require($taskUid);
        $this->requireSameOrganization($task->organizationId);
        if ($task->assignedTo === null) {
            throw new WireException($this->_('Assign the task before scheduling an email reminder.'));
        }

        $recipient = $this->wire()->users->get($task->assignedTo);
        $recipientEmail = strtolower(trim((string) $recipient->email));
        if (!$recipient->id || filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false) {
            throw new WireException($this->_('The assigned user needs a valid email address.'));
        }

        $rawRemindAt = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('remind_at')
        );
        if ($rawRemindAt === '') {
            throw new WireException($this->_('Reminder date is required.'));
        }
        try {
            $remindAt = new \DateTimeImmutable($rawRemindAt);
        } catch (\Throwable) {
            throw new WireException($this->_('Reminder date is invalid.'));
        }

        $fromAddress = strtolower(trim((string) $this->wire()->config->adminEmail));
        if (filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false) {
            $fromAddress = 'notifications@kontor.local';
        }
        $result = $this->taskModule()->reminderDispatcher()->scheduleEmail(
            organizationUid: $task->organizationId,
            taskUid: $task->uid->toString(),
            taskTitle: $task->title,
            remindAt: $remindAt,
            recipientUserId: (int) $recipient->id,
            recipientEmail: $recipientEmail,
            fromAddress: $fromAddress,
            createdBy: (int) $this->wire()->user->id,
        );

        $this->audit(
            'tasks',
            'task_reminder',
            $result['reminder']->uid->toString(),
            'scheduled',
            current: [
                'taskUid' => $task->uid->toString(),
                'remindAt' => $remindAt->format(DATE_ATOM),
                'channel' => 'email',
                'jobUid' => $result['jobUid'],
            ],
        );
        $this->message($this->_('Email reminder scheduled.'));
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($task->uid->toString()));
    }

    public function ___executeCollaboration(): string
    {
        $this->requireCollaboration();
        $this->requirePermission('kontor-collaboration-comment-view');
        $module = $this->collaborationModule();
        $this->setPageTitle($this->_('Kontor · Collaboration'));

        $canViewNotes = $this->wire()->user->isSuperuser()
            || $this->wire()->user->hasPermission('kontor-collaboration-note-view');
        $notes = $canViewNotes
            ? $module->noteRepository()->findRecent($this->organizationUid(), 50)
            : [];
        $comments = $module->commentRepository()->findRecent($this->organizationUid(), 50);
        $records = array_merge($notes, $comments);
        $selectedView = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('view'),
            ['all', 'comments', 'notes'],
        ) ?: 'all';
        if (!$canViewNotes && $selectedView === 'notes') {
            $selectedView = 'all';
        }

        return $this->renderTemplate('collaboration', [
            'notes' => $notes,
            'comments' => $comments,
            'entityLinks' => $this->collaborationEntityLinks($records),
            'authorLabels' => $this->collaborationAuthorLabels($records),
            'canViewNotes' => $canViewNotes,
            'canViewTasks' => $this->tasksReady() && $this->can('kontor-tasks-task-view'),
            'canCreateTasks' => $this->tasksReady() && $this->can('kontor-tasks-task-create'),
            'query' => trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q'))),
            'selectedView' => $selectedView,
        ]);
    }

    public function ___executeCollaborationPost(): void
    {
        $this->requirePost();
        $this->requireCollaboration();
        $kind = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('kind'),
            ['note', 'comment']
        );
        $this->requireAction($kind, ['note', 'comment']);
        $this->requirePermission($kind === 'note'
            ? 'kontor-collaboration-note-create'
            : 'kontor-collaboration-comment-create');
        $entityType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('entity_type'),
            ['task']
        );
        $this->requireAction($entityType, ['task']);
        $entityUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('entity_uid'));
        $task = $this->taskModule()->taskRepository()->require($entityUid);
        $this->requireSameOrganization($task->organizationId);
        $body = trim((string) $this->wire()->input->post('body'));

        if ($body === '') {
            throw new WireException($this->_('Collaboration text cannot be empty.'));
        }
        $body = mb_substr($body, 0, 10000);
        $module = $this->collaborationModule();
        $notificationCount = 0;

        if ($kind === 'note') {
            $record = Note::create(
                $this->organizationUid(),
                $entityType,
                $entityUid,
                $body,
                (int) $this->wire()->user->id,
            );
            $module->noteRepository()->save($record);
        } else {
            $parentUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('parent_uid')
            );
            if ($parentUid !== '') {
                $parent = $module->commentRepository()->require($parentUid);
                if (
                    !hash_equals($parent->organizationId, $this->organizationUid())
                    || $parent->entityType !== $entityType
                    || !hash_equals($parent->entityUid, $entityUid)
                ) {
                    throw new WirePermissionException($this->_('Reply target does not belong to this thread.'));
                }
            }
            $record = $module->commentService()->post(
                $this->organizationUid(),
                $entityType,
                $entityUid,
                $body,
                (int) $this->wire()->user->id,
                $parentUid !== '' ? $parentUid : null,
            );
            $notificationCount = count($this->queueCollaborationNotifications($record, $task->title));
        }

        $this->audit(
            'collaboration',
            $kind,
            $record->uid->toString(),
            'created',
            current: ['entityType' => $entityType, 'entityUid' => $entityUid],
        );
        $this->message(match (true) {
            $kind === 'note' => $this->_('Note added.'),
            $notificationCount > 0 => sprintf(
                $this->_('Comment posted; %d notification(s) queued.'),
                $notificationCount,
            ),
            default => $this->_('Comment posted.'),
        });
        $this->wire()->session->redirect('../task/?id=' . rawurlencode($entityUid));
    }

    public function ___executeCollaborationAction(): void
    {
        $this->requirePost();
        $this->requireCollaboration();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive_note', 'archive_comment', 'toggle_follow']
        );
        $this->requireAction($action, ['archive_note', 'archive_comment', 'toggle_follow']);
        $entityUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('entity_uid'));
        $task = $this->taskModule()->taskRepository()->require($entityUid);
        $this->requireSameOrganization($task->organizationId);
        $module = $this->collaborationModule();

        if ($action === 'toggle_follow') {
            $this->requirePermission('kontor-collaboration-follow-manage');
            $userId = (int) $this->wire()->user->id;
            $following = $module->followerRepository()->isFollowing(
                $this->organizationUid(),
                'task',
                $entityUid,
                $userId,
            );
            if ($following) {
                $module->followerRepository()->unfollow(
                    $this->organizationUid(),
                    'task',
                    $entityUid,
                    $userId,
                );
            } else {
                $module->followerRepository()->follow(
                    $this->organizationUid(),
                    'task',
                    $entityUid,
                    $userId,
                );
            }
            $this->audit(
                'collaboration',
                'follower',
                $entityUid,
                $following ? 'unfollowed' : 'followed',
            );
            $this->message($following ? $this->_('Thread unfollowed.') : $this->_('Thread followed.'));
        } else {
            $kind = $action === 'archive_note' ? 'note' : 'comment';
            $this->requirePermission('kontor-collaboration-' . $kind . '-archive');
            $recordUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('record_uid'));
            $repository = $kind === 'note'
                ? $module->noteRepository()
                : $module->commentRepository();
            $record = $repository->require($recordUid);
            if (
                !hash_equals($record->organizationId, $this->organizationUid())
                || $record->entityType !== 'task'
                || !hash_equals($record->entityUid, $entityUid)
            ) {
                throw new WirePermissionException($this->_('Collaboration record does not belong to this task.'));
            }
            $repository->archive($recordUid);
            $this->audit('collaboration', $kind, $recordUid, 'archived');
            $this->message($kind === 'note' ? $this->_('Note archived.') : $this->_('Comment archived.'));
        }

        $this->wire()->session->redirect('../task/?id=' . rawurlencode($entityUid));
    }

    public function ___executeReports(): string
    {
        $this->requireReports();
        $this->requirePermission('kontor-reports-report-view');
        $this->setPageTitle($this->_('Kontor · Reports'));
        $module = $this->reportsModule();
        $providers = $module->providerRegistry()->all();
        $requestedKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->get('provider')
        );
        $providerKey = $requestedKey !== '' ? $requestedKey : (string) array_key_first($providers);
        $provider = $providerKey !== '' && $module->providerRegistry()->has($providerKey)
            ? $module->providerRegistry()->get($providerKey)
            : null;
        $filters = [];
        $groupBy = [];
        $result = null;
        $fieldOptions = [];
        $fieldLabels = [];

        if ($providerKey === 'crm_pipeline' && $this->crmReady()) {
            /** @var KontorCRM $crm */
            $crm = $this->wire()->modules->get('KontorCRM');
            $pipelineOptions = [];
            $stageOptions = [];
            foreach ($crm->pipelineRepository()->forOrganization($this->organizationUid()) as $pipeline) {
                $pipelineUid = $pipeline->uid->toString();
                $pipelineOptions[$pipelineUid] = $pipeline->name;
                foreach ($crm->stageRepository()->forPipeline($pipelineUid) as $stage) {
                    $stageOptions[$stage->uid->toString()] = $pipeline->name . ' · '
                        . ($stage->displayNameIn('en') ?? ucwords(str_replace('_', ' ', $stage->nameKey)));
                }
            }
            $fieldOptions = [
                'pipeline_uid' => $pipelineOptions,
                'stage_uid' => $stageOptions,
                'status' => ['open' => $this->_('Open'), 'won' => $this->_('Won'), 'lost' => $this->_('Lost')],
            ];
            $fieldLabels = [
                'pipeline_uid' => $this->_('Sales pipeline'),
                'stage_uid' => $this->_('Sales stage'),
                'status' => $this->_('Deal status'),
                'deal_count' => $this->_('Deals'),
                'total_value_minor' => $this->_('Pipeline value'),
            ];
        }

        if ($provider !== null) {
            $schema = $provider->schema();
            foreach ($schema->filterableFields as $field) {
                $value = trim($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->get('filter_' . $field)
                ));
                if ($value !== '') {
                    $filters[$field] = $value;
                }
            }
            $requestedGroup = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->get('group_by')
            );
            if ($requestedGroup !== '' && in_array($requestedGroup, $schema->groupableFields, true)) {
                $groupBy[] = $requestedGroup;
            }

            if ((string) $this->wire()->input->get('run') === '1') {
                $result = $module->reportBuilder()->run(
                    $providerKey,
                    new ReportQuery($this->organizationUid(), $filters, $groupBy),
                );
                $this->audit(
                    'reports',
                    'report',
                    $providerKey,
                    'run',
                    metadata: ['filters' => $filters, 'groupBy' => $groupBy, 'rowCount' => count($result->rows)],
                );
            }
        }

        return $this->renderTemplate('reports', [
            'providers' => $providers,
            'providerKey' => $providerKey,
            'provider' => $provider,
            'filters' => $filters,
            'groupBy' => $groupBy,
            'result' => $result,
            'fieldOptions' => $fieldOptions,
            'fieldLabels' => $fieldLabels,
            'currencyCode' => $this->organization()->defaultCurrency,
            'schedules' => $module->scheduledReportRepository()->forOrganization(
                $this->organizationUid()
            ),
            'canManageSchedules' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-reports-schedule-manage'),
            'canExport' => $this->wire()->user->isSuperuser()
                || $this->wire()->user->hasPermission('kontor-reports-report-export'),
        ]);
    }

    public function ___executeReportsSchedule(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $module = $this->reportsModule();
        $providerKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('provider')
        );
        if (!$module->providerRegistry()->has($providerKey)) {
            throw new WireException($this->_('Unknown report provider.'));
        }

        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        if ($name === '') {
            throw new WireException($this->_('A schedule name is required.'));
        }
        $recurrence = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('recurrence'),
            ['daily', 'weekly', 'monthly', 'yearly'],
        );
        $format = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('format'),
            ['csv', 'json', 'xlsx', 'pdf'],
        );
        if ($recurrence === null || $format === null) {
            throw new WireException($this->_('Invalid schedule settings.'));
        }

        try {
            $filters = json_decode(
                (string) $this->wire()->input->post('filters_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
            $groupBy = json_decode(
                (string) $this->wire()->input->post('group_by_json'),
                true,
                flags: JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        if (!is_array($filters) || !is_array($groupBy)) {
            throw new WireException($this->_('Invalid report parameters.'));
        }

        $firstRunInput = trim((string) $this->wire()->input->post('first_run_at'));
        try {
            $firstRunAt = $firstRunInput !== ''
                ? new \DateTimeImmutable($firstRunInput)
                : new \DateTimeImmutable();
        } catch (\Exception) {
            throw new WireException($this->_('Invalid first run time.'));
        }

        $schedule = $module->scheduledReports()->schedule(
            organizationUid: $this->organizationUid(),
            providerKey: $providerKey,
            name: $name,
            recurrenceRule: $recurrence,
            filters: $filters,
            groupBy: array_values(array_map('strval', $groupBy)),
            format: $format,
            firstRunAt: $firstRunAt,
            createdBy: (int) $this->wire()->user->id,
        );
        $this->audit(
            'reports',
            'scheduled_report',
            $schedule->uid->toString(),
            'created',
            current: [
                'provider' => $providerKey,
                'recurrence' => $recurrence,
                'format' => $format,
                'nextRunAt' => $firstRunAt->format(DATE_ATOM),
            ],
        );
        $this->message($this->_('Scheduled report created.'));
        $this->wire()->session->redirect('../reports/?provider='.rawurlencode($providerKey));
    }

    public function ___executeReportsDispatchDue(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $jobs = $this->reportsModule()->scheduledReportDispatcher()->dispatchDue(
            $this->organizationUid()
        );
        $this->audit(
            'reports',
            'scheduled_report',
            $this->organizationUid(),
            'dispatched',
            metadata: ['jobCount' => count($jobs), 'jobUids' => array_values($jobs)],
        );
        $this->message(sprintf($this->_('%d due report job(s) queued.'), count($jobs)));
        $this->wire()->session->redirect('../reports/');
    }

    public function ___executeReportsScheduleAction(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-schedule-manage');
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('uid'));
        $schedule = $this->reportsModule()->scheduledReportRepository()->require($uid);
        if (!hash_equals($schedule->organizationId, $this->organizationUid())) {
            throw new WirePermissionException($this->_('Scheduled report does not belong to this organization.'));
        }

        $this->reportsModule()->scheduledReportRepository()->archive($uid);
        $this->audit('reports', 'scheduled_report', $uid, 'archived');
        $this->message($this->_('Scheduled report archived.'));
        $this->wire()->session->redirect('../reports/');
    }

    public function ___executeReportsExport(): void
    {
        $this->requirePost();
        $this->requireReports();
        $this->requirePermission('kontor-reports-report-export');
        $module = $this->reportsModule();
        $providerKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('provider')
        );
        if (!$module->providerRegistry()->has($providerKey)) {
            throw new WireException($this->_('Unknown report provider.'));
        }

        try {
            $filters = json_decode(
                (string) $this->wire()->input->post('filters_json'),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
            $groupBy = json_decode(
                (string) $this->wire()->input->post('group_by_json'),
                true,
                16,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        if (!is_array($filters) || !is_array($groupBy)) {
            throw new WireException($this->_('Invalid report parameters.'));
        }
        foreach ($filters as $field => $value) {
            if (!is_string($field) || !is_scalar($value)) {
                throw new WireException($this->_('Invalid report filter.'));
            }
            $filters[$field] = mb_substr(trim((string) $value), 0, 191);
        }
        $groupBy = array_values(array_filter(
            $groupBy,
            static fn (mixed $field): bool => is_string($field),
        ));
        $result = $module->reportBuilder()->run(
            $providerKey,
            new ReportQuery($this->organizationUid(), $filters, $groupBy),
        );
        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_report_');
        if ($temporary === false) {
            throw new WireException($this->_('Could not create a report export file.'));
        }
        $path = $temporary . '.csv';
        rename($temporary, $path);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });
        $fields = array_keys($module->providerRegistry()->get($providerKey)->schema()->fields);
        $module->reportExporter()->export($result, $fields, 'csv', $path);
        $this->audit(
            'reports',
            'report',
            $providerKey,
            'exported',
            metadata: ['format' => 'csv', 'rowCount' => count($result->rows)],
        );
        wireSendFile($path, [
            'forceDownload' => true,
            'downloadFilename' => preg_replace('/[^a-z0-9_-]+/i', '-', $providerKey) . '-report.csv',
            'exit' => true,
        ]);
    }
}
