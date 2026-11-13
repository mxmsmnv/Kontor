<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Domain\Project;

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
                'Find a project UID for use in task operations. Search matches the code or name among the 500 most recently updated projects.',
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
                'Find the code, name and status of a project in this organization by UID.',
                [$this, 'mcpKontorProjectGet'],
                'read',
                ['uid' => $this->uidSchema()],
                ['uid'],
            ),
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
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('Project UID must be a valid ULID.');
        }

        $project = $module->projectRepository()->find($uid);
        if ($project === null || !hash_equals($this->mcpProjectsOrganizationUid(), $project->organizationId)) {
            throw new Wire404Exception('Project was not found.');
        }

        return $this->mcpProjectSummary($project);
    }

    private function mcpProjectsModule(): KontorProjects
    {
        $actor = $this->mcpActor();
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-projects-project-view'))) {
            throw new WirePermissionException('The linked user cannot view Kontor projects.');
        }

        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorProjects')) {
            throw new Wire404Exception('Kontor Projects is unavailable.');
        }

        return $modules->get('KontorProjects');
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
