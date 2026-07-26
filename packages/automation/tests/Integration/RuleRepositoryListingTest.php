<?php

declare(strict_types=1);

namespace Kontor\Automation\Tests\Integration;

use Kontor\Automation\Domain\AutomationRule;
use Kontor\Automation\Infrastructure\Persistence\RuleRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class RuleRepositoryListingTest extends DatabaseTestCase
{
    public function test_for_organization_excludes_archived_rules(): void
    {
        $rules = new RuleRepository(
            $this->pdo,
            new OrganizationRepository($this->pdo),
        );
        $visible = AutomationRule::create(
            $this->organizationUid,
            'Visible',
            'demo.visible',
        );
        $archived = AutomationRule::create(
            $this->organizationUid,
            'Archived',
            'demo.archived',
        );
        $rules->save($visible);
        $rules->save($archived);
        $rules->archive($archived->uid->toString());

        $result = $rules->forOrganization($this->organizationUid);

        $this->assertCount(1, $result);
        $this->assertSame('Visible', $result[0]->name);
    }
}
