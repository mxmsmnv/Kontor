<?php

declare(strict_types=1);

namespace Kontor\Projects\Tests\Integration;

use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Infrastructure\Persistence\ProjectRepository;

final class ProjectRepositoryTest extends DatabaseTestCase
{
    public function test_for_organization_is_tenant_scoped_and_excludes_archived_projects(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $projects = new ProjectRepository($this->pdo, $organizations);
        $visible = Project::create($this->organizationUid, 'VISIBLE', 'Visible project');
        $archived = Project::create($this->organizationUid, 'ARCHIVED', 'Archived project');
        $otherOrganization = Organization::createDefault('DE', 'de', 'EUR');
        $otherOrganization->name = 'Other organization';
        $organizations->save($otherOrganization);
        $other = Project::create($otherOrganization->uid->toString(), 'OTHER', 'Other project');

        $projects->save($visible);
        $projects->save($archived);
        $projects->save($other);
        $projects->archive($archived->uid->toString());

        $result = $projects->forOrganization($this->organizationUid);

        $this->assertCount(1, $result);
        $this->assertSame('VISIBLE', $result[0]->code);
    }
}
