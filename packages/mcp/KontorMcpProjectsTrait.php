<?php

namespace ProcessWire;

use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Domain\BillableItem;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\SDK\ValueObjects\Money;

trait KontorMcpProjectsTrait
{
    private function mcpProjectTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorProjects')) {
            return [];
        }

        return [
            $this->tool(
                'kontor_projects_list',
                'List Kontor projects',
                'Find projects by code or name among the 500 most recently updated projects.',
                [$this, 'mcpKontorProjectsList'],
                'read',
                [
                    'query' => ['type' => 'string', 'maxLength' => 120],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                ],
            ),
            $this->tool(
                'kontor_project_get',
                'Get a Kontor project',
                'Read a project code, name, status, dates and billing defaults by UID.',
                [$this, 'mcpKontorProjectGet'],
                'read',
                ['uid' => $this->uidSchema()],
                ['uid'],
            ),
            ...$this->mcpProjectMutationTools(),
        ];
    }

    public function mcpKontorProjectsList(string $query = '', int $limit = 20): array
    {
        $module = $this->mcpProjectsModule();
        $query = trim($query);
        if (mb_strlen($query) > 120 || $limit < 1 || $limit > 50) {
            throw new WireException('Invalid project search query or limit.');
        }

        $organizationUid = $this->mcpProjectsOrganizationUid();
        $projects = $module->projectRepository()->forOrganization($organizationUid, $query === '' ? $limit : 500);
        if ($query !== '') {
            $projects = array_filter($projects, static fn (Project $project): bool =>
                mb_stripos($project->code, $query) !== false || mb_stripos($project->name, $query) !== false);
        }

        $items = array_map($this->mcpProjectSummary(...), array_slice($projects, 0, $limit));
        return ['items' => $items, 'count' => count($items)];
    }

    public function mcpKontorProjectGet(string $uid): array
    {
        $module = $this->mcpProjectsModule();
        $project = $this->requireMcpProject($module, $uid, $this->mcpProjectsOrganizationUid());
        return $this->mcpProjectSummary($project) + [
            'startDate' => $project->startDate?->format('Y-m-d'),
            'endDate' => $project->endDate?->format('Y-m-d'),
            'hourlyRateMinor' => $project->defaultHourlyRateMinor,
            'currencyCode' => $project->currencyCode,
        ];
    }

    private function mcpProjectsModule(): KontorProjects
    {
        $this->requireMcpProjectActor('project-view');
        return $this->getMcpProjectsModule();
    }

    private function requireMcpProjectActor(string $permission): User
    {
        $actor = $this->mcpActor();
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-projects-' . $permission))) {
            throw new WirePermissionException('The linked user cannot perform this project operation.');
        }
        return $actor;
    }

    private function getMcpProjectsModule(): KontorProjects
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorProjects')) {
            throw new Wire404Exception('Kontor Projects is unavailable.');
        }

        return $modules->get('KontorProjects');
    }

    private function mcpProjectMutationTools(): array
    {
        $uid = $this->uidSchema();
        $key = $this->idempotencySchema();
        $customerType = ['type' => 'string', 'enum' => ['company', 'contact']];
        $date = ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$'];
        $projectFields = [
            'name' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 191],
            'customer_type' => $customerType,
            'customer_uid' => $uid,
            'hourly_rate_minor' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000000],
            'currency_code' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$'],
        ];
        return [
            $this->tool('kontor_project_create', 'Create a Kontor project',
                'Create a project for an accessible CRM customer. Use minor currency units for the hourly rate.',
                $this->mcpProjectCreate(...), 'draft',
                ['code' => ['type' => 'string', 'pattern' => '^[A-Z0-9_-]{1,50}$'], 'idempotency_key' => $key] + $projectFields,
                ['code', 'name', 'customer_type', 'customer_uid', 'hourly_rate_minor', 'currency_code', 'idempotency_key']),
            $this->tool('kontor_project_update', 'Edit a Kontor project',
                'Edit supplied project fields. Omitted fields are preserved. Provide customer type and UID together.',
                $this->mcpProjectUpdate(...), 'draft', [
                    'uid' => $uid,
                    'fields' => ['type' => 'object', 'minProperties' => 1, 'additionalProperties' => false, 'properties' => $projectFields],
                    'idempotency_key' => $key,
                ], ['uid', 'fields', 'idempotency_key']),
            $this->tool('kontor_project_archive_action', 'Archive or restore a Kontor project',
                'Apply the reviewed archive or restore action to one project.',
                $this->mcpProjectArchiveAction(...), 'draft', [
                    'uid' => $uid,
                    'action' => ['type' => 'string', 'enum' => ['archive', 'restore']],
                    'idempotency_key' => $key,
                    'confirmation' => ['type' => 'string', 'const' => 'APPLY_PROJECT_ARCHIVE_ACTION'],
                ], ['uid', 'action', 'idempotency_key', 'confirmation'], destructive: true),
            $this->tool('kontor_project_milestones_list', 'List Kontor project milestones',
                'List up to 50 milestones for one accessible project.',
                $this->mcpProjectMilestonesList(...), 'read', [
                    'project_uid' => $uid,
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ], ['project_uid']),
            $this->tool('kontor_project_milestone_create', 'Create a Kontor milestone',
                'Create a milestone on a project.',
                $this->mcpProjectMilestoneCreate(...), 'draft', [
                    'project_uid' => $uid,
                    'name' => $projectFields['name'],
                    'idempotency_key' => $key,
                    'due_date' => $date,
                ], ['project_uid', 'name', 'idempotency_key']),
            $this->tool('kontor_project_milestone_action', 'Complete or reopen a Kontor milestone',
                'Change the status of a milestone on its owning project.',
                $this->mcpProjectMilestoneAction(...), 'draft', [
                    'project_uid' => $uid,
                    'milestone_uid' => $uid,
                    'action' => ['type' => 'string', 'enum' => ['complete', 'reopen']],
                    'idempotency_key' => $key,
                ], ['project_uid', 'milestone_uid', 'action', 'idempotency_key']),
            $this->tool('kontor_project_time_log', 'Log manual project time',
                'Record time ending now for the linked user, optionally against a project milestone.',
                $this->mcpProjectTimeLog(...), 'draft', [
                    'project_uid' => $uid,
                    'description' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                    'minutes' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 14400],
                    'idempotency_key' => $key,
                    'milestone_uid' => $uid,
                ], ['project_uid', 'description', 'minutes', 'idempotency_key']),
            $this->tool('kontor_project_billable_item_create', 'Add a billable item to a project',
                'Record a billable item using the project currency and a price in minor units.',
                $this->mcpProjectBillableItemCreate(...), 'draft', [
                    'project_uid' => $uid,
                    'description' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 500],
                    'quantity' => ['type' => 'number', 'minimum' => 0.000001, 'maximum' => 1000000],
                    'unit_price_minor' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000000000],
                    'idempotency_key' => $key,
                    'milestone_uid' => $uid,
                ], ['project_uid', 'description', 'quantity', 'unit_price_minor', 'idempotency_key']),
        ];
    }

    public function mcpProjectCreate(
        string $code,
        string $name,
        string $customer_type,
        string $customer_uid,
        int $hourly_rate_minor,
        string $currency_code,
        string $idempotency_key,
    ): array {
        $actor = $this->requireMcpProjectActor('project-create');
        if (preg_match('/^[A-Z0-9_-]{1,50}$/D', $code) !== 1) {
            throw new WireException('Use a project code of 1–50 letters, numbers, hyphens or underscores.');
        }
        $fields = $this->normalizeMcpProjectFields([
            'name' => $name, 'customer_type' => $customer_type, 'customer_uid' => $customer_uid,
            'hourly_rate_minor' => $hourly_rate_minor, 'currency_code' => $currency_code,
        ], $actor);

        return $this->writeMcpProject($actor, null, 'created', $idempotency_key, ['code' => $code] + $fields,
            function (KontorProjects $module, ?Project $unused, string $organizationUid) use ($code, $fields, $actor): array {
                $project = Project::create($organizationUid, $code, $fields['name'],
                    $fields['customer_type'], $fields['customer_uid'], $fields['hourly_rate_minor'],
                    $fields['currency_code'], (int) $actor->id);
                try {
                    $module->projectRepository()->insert($project);
                } catch (\PDOException $error) {
                    if (in_array($error->getCode(), ['23000', '23505'], true)) {
                        throw new WireException('That project code is already in use.');
                    }
                    throw $error;
                }
                return ['project', $project->uid->toString()];
            });
    }

    public function mcpProjectUpdate(string $uid, array $fields, string $idempotency_key): array
    {
        $actor = $this->requireMcpProjectActor('project-edit');
        $fields = $this->normalizeMcpProjectFields($fields, $actor);
        return $this->writeMcpProject($actor, $uid, 'updated', $idempotency_key, $fields,
            static function (KontorProjects $module, Project $project) use ($fields): array {
                $properties = ['name' => 'name', 'customer_type' => 'customerType', 'customer_uid' => 'customerUid',
                    'hourly_rate_minor' => 'defaultHourlyRateMinor', 'currency_code' => 'currencyCode'];
                foreach ($fields as $field => $value) {
                    $project->{$properties[$field]} = $value;
                }
                $project->updatedAt = new \DateTimeImmutable();
                $module->projectRepository()->save($project);
                return ['project', $project->uid->toString()];
            });
    }

    public function mcpProjectArchiveAction(string $uid, string $action, string $idempotency_key, string $confirmation): array
    {
        $actor = $this->requireMcpProjectActor('project-archive');
        if ($confirmation !== 'APPLY_PROJECT_ARCHIVE_ACTION' || !in_array($action, ['archive', 'restore'], true)) {
            throw new WireException('Confirm the project archive or restore action.');
        }
        $archive = $action === 'archive';
        return $this->writeMcpProject($actor, $uid, $archive ? 'archived' : 'restored', $idempotency_key, [],
            static function (KontorProjects $module, Project $project) use ($uid, $archive): array {
                if ($archive) {
                    $module->projectRepository()->archive($uid);
                } else {
                    $module->projectRepository()->restore($uid);
                }
                return ['project', $uid];
            });
    }

    public function mcpProjectMilestonesList(string $project_uid, int $offset = 0): array
    {
        $module = $this->mcpProjectsModule();
        if ($offset < 0 || $offset > 10000) {
            throw new WireException('Invalid milestone offset.');
        }
        $project = $this->requireMcpProject($module, $project_uid, $this->mcpProjectsOrganizationUid());
        $milestones = $module->milestoneRepository()->pageForProject($project->organizationId, $project_uid, $offset);
        return [
            'items' => array_map(static fn (ProjectMilestone $milestone): array => [
                'uid' => $milestone->uid->toString(), 'name' => $milestone->name,
                'dueDate' => $milestone->dueDate?->format('Y-m-d'), 'status' => $milestone->status,
            ], array_slice($milestones, 0, 50)),
            'nextOffset' => count($milestones) > 50 ? $offset + 50 : null,
        ];
    }

    public function mcpProjectMilestoneCreate(string $project_uid, string $name, string $idempotency_key, ?string $due_date = null): array
    {
        $actor = $this->requireMcpProjectActor('milestone-manage');
        $name = $this->normalizeMcpProjectText($name, 191);
        $due = $due_date === null ? null : \DateTimeImmutable::createFromFormat('!Y-m-d', $due_date);
        if ($due_date !== null && ($due === false || $due?->format('Y-m-d') !== $due_date)) {
            throw new WireException('Use a valid milestone due date in YYYY-MM-DD format.');
        }
        return $this->writeMcpProject($actor, $project_uid, 'created', $idempotency_key, ['name' => $name, 'dueDate' => $due_date],
            static function (KontorProjects $module, Project $project) use ($project_uid, $name, $due): array {
                $milestone = ProjectMilestone::create($project->organizationId, $project_uid, $name, $due);
                $module->milestoneRepository()->save($milestone);
                return ['milestone', $milestone->uid->toString()];
            });
    }

    public function mcpProjectMilestoneAction(string $project_uid, string $milestone_uid, string $action, string $idempotency_key): array
    {
        $actor = $this->requireMcpProjectActor('milestone-manage');
        if (!in_array($action, ['complete', 'reopen'], true)) {
            throw new WireException('Unknown milestone action.');
        }
        return $this->writeMcpProject($actor, $project_uid, $action, $idempotency_key, ['milestoneUid' => $milestone_uid],
            function (KontorProjects $module, Project $project) use ($milestone_uid, $action): array {
                $this->requireMcpProjectMilestone($module, $project, $milestone_uid);
                if ($action === 'complete') {
                    $module->milestones()->complete($milestone_uid);
                } else {
                    $module->milestones()->reopen($milestone_uid);
                }
                return ['milestone', $milestone_uid];
            });
    }

    public function mcpProjectTimeLog(
        string $project_uid,
        string $description,
        int $minutes,
        string $idempotency_key,
        ?string $milestone_uid = null,
    ): array {
        $actor = $this->requireMcpProjectActor('time-track');
        $description = $this->normalizeMcpProjectText($description, 500);
        if ($minutes < 1 || $minutes > 14400) {
            throw new WireException('Use a duration from 1 to 14,400 minutes.');
        }
        return $this->writeMcpProject($actor, $project_uid, 'logged', $idempotency_key,
            ['description' => $description, 'minutes' => $minutes, 'milestoneUid' => $milestone_uid],
            function (KontorProjects $module, Project $project) use ($project_uid, $description, $minutes, $actor, $milestone_uid): array {
                if ($milestone_uid !== null) {
                    $this->requireMcpProjectMilestone($module, $project, $milestone_uid);
                }
                $end = new \DateTimeImmutable();
                $entry = $module->timeTracking()->logManual($project->organizationId, $project_uid, (int) $actor->id,
                    $end->modify("-{$minutes} minutes"), $end, $description, $milestone_uid, true, null, $project->currencyCode);
                return ['time_entry', $entry->uid->toString()];
            });
    }

    public function mcpProjectBillableItemCreate(
        string $project_uid,
        string $description,
        float $quantity,
        int $unit_price_minor,
        string $idempotency_key,
        ?string $milestone_uid = null,
    ): array {
        $actor = $this->requireMcpProjectActor('billable-item-manage');
        $description = $this->normalizeMcpProjectText($description, 500);
        if (!is_finite($quantity) || $quantity < 0.000001 || $quantity > 1000000
            || round($quantity, 6) !== $quantity || $unit_price_minor < 0 || $unit_price_minor > 1000000000) {
            throw new WireException('Use a positive quantity with at most six decimal places and a valid unit price.');
        }
        return $this->writeMcpProject($actor, $project_uid, 'created', $idempotency_key,
            ['description' => $description, 'quantity' => $quantity, 'priceMinor' => $unit_price_minor, 'milestoneUid' => $milestone_uid],
            function (KontorProjects $module, Project $project) use ($project_uid, $description, $quantity, $unit_price_minor, $milestone_uid): array {
                if ($project->currencyCode === null) {
                    throw new WireException('Configure the project currency before adding billable items.');
                }
                if ($milestone_uid !== null) {
                    $this->requireMcpProjectMilestone($module, $project, $milestone_uid);
                }
                $item = BillableItem::create($project->organizationId, $project_uid, $description, $quantity,
                    Money::ofMinor($unit_price_minor, $project->currencyCode), $milestone_uid);
                $module->billableItemRepository()->save($item);
                return ['billable_item', $item->uid->toString()];
            });
    }

    private function normalizeMcpProjectFields(array $fields, User $actor): array
    {
        if ($fields === [] || array_diff_key($fields, array_flip(['name', 'customer_type', 'customer_uid', 'hourly_rate_minor', 'currency_code'])) !== []) {
            throw new WireException('Provide supported project fields.');
        }
        if (array_key_exists('name', $fields)) {
            $fields['name'] = $this->normalizeMcpProjectText($fields['name'], 191);
        }
        if (array_key_exists('hourly_rate_minor', $fields)
            && (!is_int($fields['hourly_rate_minor']) || $fields['hourly_rate_minor'] < 1 || $fields['hourly_rate_minor'] > 1000000000)) {
            throw new WireException('Hourly rate must be a positive amount in minor currency units.');
        }
        if (array_key_exists('currency_code', $fields)
            && (!is_string($fields['currency_code']) || preg_match('/^[A-Z]{3}$/D', $fields['currency_code']) !== 1)) {
            throw new WireException('Currency must be a three-letter uppercase code.');
        }
        if (array_key_exists('customer_type', $fields) || array_key_exists('customer_uid', $fields)) {
            $type = $fields['customer_type'] ?? '';
            $uid = $fields['customer_uid'] ?? '';
            if (!in_array($type, ['contact', 'company'], true) || !is_string($uid) || !$this->validMcpProjectUid($uid)) {
                throw new WireException('Provide the customer type and a valid customer UID together.');
            }
            if (!$actor->isSuperuser() && !$actor->hasPermission('kontor-contacts-' . $type . '-view')) {
                throw new WirePermissionException('The linked user cannot view this customer type.');
            }
            $modules = $this->wire()->modules;
            if (!$modules->isInstalled('KontorContacts')) {
                throw new Wire404Exception('Kontor Contacts is unavailable.');
            }
            $contacts = $modules->get('KontorContacts');
            $repository = $type === 'contact' ? $contacts->contactRepository() : $contacts->companyRepository();
            $customer = $repository->find($uid);
            if ($customer === null || !hash_equals($this->mcpProjectsOrganizationUid(), $customer->organizationId)) {
                throw new Wire404Exception('Project customer was not found.');
            }
        }
        ksort($fields);
        return $fields;
    }

    private function normalizeMcpProjectText(mixed $value, int $limit): string
    {
        if (!is_string($value) || mb_strlen($value) > $limit || ($text = trim(strip_tags($value))) === '') {
            throw new WireException("Use nonempty project text up to {$limit} characters.");
        }
        return $text;
    }

    private function validMcpProjectUid(string $uid): bool
    {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) === 1;
    }

    private function requireMcpProject(KontorProjects $module, string $uid, string $organizationUid, bool $lock = false): Project
    {
        if (!$this->validMcpProjectUid($uid)) {
            throw new WireException('Project UID must be a valid ULID.');
        }
        $project = $module->projectRepository()->find($uid, $lock);
        if ($project === null || !hash_equals($organizationUid, $project->organizationId)) {
            throw new Wire404Exception('Project was not found.');
        }
        return $project;
    }

    private function requireMcpProjectMilestone(KontorProjects $module, Project $project, string $uid): void
    {
        if (!$this->validMcpProjectUid($uid)) {
            throw new WireException('Milestone UID must be a valid ULID.');
        }
        $milestone = $module->milestoneRepository()->find($uid);
        if ($milestone === null || !hash_equals($project->uid->toString(), $milestone->projectUid)
            || !hash_equals($project->organizationId, $milestone->organizationId)) {
            throw new Wire404Exception('Milestone was not found on this project.');
        }
    }

    private function writeMcpProject(User $actor, ?string $projectUid, string $action, string $key, array $args, callable $operation): array
    {
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('Use an idempotency key of 8 to 160 safe characters.');
        }
        $module = $this->getMcpProjectsModule();
        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $organizationUid = $this->mcpProjectsOrganizationUid();
        $pdo = $container->get(\PDO::class);
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $project = $projectUid === null ? null : $this->requireMcpProject($module, $projectUid, $organizationUid, true);
            $receipt = $idempotency->remember(
                $organizationUid,
                'mcp:project:' . $actor->id . ':' . hash('sha256', $key),
                ['action' => $action, 'projectUid' => $projectUid, 'arguments' => $args],
                static function () use ($operation, $module, $project, $organizationUid, $container, $actor, $action): array {
                    [$entity, $uid] = $operation($module, $project, $organizationUid);
                    $organizationId = $container->get(OrganizationRepository::class)->internalIdOf($organizationUid);
                    $container->get(AuditLogger::class)->record(
                        $organizationId, 'projects', $entity, $uid, $action, 'user', (string) $actor->id,
                        metadata: ['transport' => 'mcp'],
                    );
                    return ['status' => 200, 'body' => ['uid' => $uid, 'entity' => $entity, 'action' => $action]];
                },
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $receipt->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof WireException) {
                throw $error;
            }
            return $this->safe(static fn () => throw $error);
        }
    }

    private function mcpProjectsOrganizationUid(): string
    {
        $kontor = $this->wire()->modules->get('Kontor');
        return $kontor->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();
    }

    private function mcpProjectSummary(Project $project): array
    {
        return [
            'uid' => $project->uid->toString(),
            'code' => $project->code,
            'name' => $project->name,
            'status' => $project->status,
        ];
    }
}
