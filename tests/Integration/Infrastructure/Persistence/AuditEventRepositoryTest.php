<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Infrastructure\Persistence;

use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Persistence\AuditEventRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Tests\Integration\DatabaseTestCase;

final class AuditEventRepositoryTest extends DatabaseTestCase
{
    public function test_find_recent_scopes_searches_and_hydrates_events(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $organization = $organizations->defaultOrganization('US', 'en', 'EUR');
        $organizationId = $organizations->internalIdOf($organization->uid->toString());
        $logger = new AuditLogger($this->pdo);
        $logger->record(
            $organizationId,
            'KontorContacts',
            'contact',
            'contact_01',
            'created',
            'user',
            'user_01',
            current: ['displayName' => 'Ada'],
            metadata: ['source' => 'admin']
        );
        $logger->record(
            $organizationId,
            'KontorContacts',
            'company',
            'company_01',
            'updated',
            'user',
            'user_01'
        );

        $events = (new AuditEventRepository($this->pdo))->findRecent($organizationId, 'contact_01');

        $this->assertCount(1, $events);
        $this->assertSame('contact_01', $events[0]->entityUid);
        $this->assertSame(['displayName' => 'Ada'], $events[0]->current);
        $this->assertSame(['source' => 'admin'], $events[0]->metadata);

        $repository = new AuditEventRepository($this->pdo);
        $filtered = $repository->findRecent(
            $organizationId,
            limit: 10,
            component: 'KontorContacts',
            entityType: 'company',
            action: 'updated',
        );
        $options = $repository->filterOptions($organizationId);
        $secondPage = $repository->findRecent(
            $organizationId,
            limit: 1,
            offset: 1,
        );

        $this->assertCount(1, $filtered);
        $this->assertSame('company_01', $filtered[0]->entityUid);
        $this->assertCount(1, $secondPage);
        $this->assertSame('contact_01', $secondPage[0]->entityUid);
        $this->assertSame(2, $repository->countMatching($organizationId));
        $this->assertSame(1, $repository->countMatching(
            $organizationId,
            component: 'KontorContacts',
            entityType: 'company',
            action: 'updated',
        ));
        $this->assertSame(['KontorContacts'], $options['components']);
        $this->assertSame(['company', 'contact'], $options['entityTypes']);
        $this->assertSame(['created', 'updated'], $options['actions']);
    }
}
