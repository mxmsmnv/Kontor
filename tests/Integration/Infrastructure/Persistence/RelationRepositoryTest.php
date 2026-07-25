<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Core\Tests\Integration\DatabaseTestCase;

final class RelationRepositoryTest extends DatabaseTestCase
{
    private string $organizationUid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizationUid = (new OrganizationRepository($this->pdo))
            ->defaultOrganization('US', 'en', 'EUR')
            ->uid
            ->toString();
    }

    private function repository(): RelationRepository
    {
        return new RelationRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_directed_relation_only_shows_from_the_source_side(): void
    {
        $relations = $this->repository();
        $relations->create($this->organizationUid, 'task', 'tsk_01', 'contact', 'ct_01', 'relates_to');

        $this->assertCount(1, $relations->relatedTo($this->organizationUid, 'task', 'tsk_01'));
        $this->assertCount(0, $relations->relatedTo($this->organizationUid, 'contact', 'ct_01'));
    }

    public function test_bidirectional_relation_shows_from_both_sides(): void
    {
        $relations = $this->repository();
        $relations->create($this->organizationUid, 'task', 'tsk_01', 'contact', 'ct_01', 'relates_to', direction: 'bidirectional');

        $this->assertCount(1, $relations->relatedTo($this->organizationUid, 'task', 'tsk_01'));
        $this->assertCount(1, $relations->relatedTo($this->organizationUid, 'contact', 'ct_01'));
    }

    public function test_archived_relations_are_excluded(): void
    {
        $relations = $this->repository();
        $uid = $relations->create($this->organizationUid, 'task', 'tsk_01', 'contact', 'ct_01', 'relates_to');

        $relations->archive($uid);
        $this->assertCount(0, $relations->relatedTo($this->organizationUid, 'task', 'tsk_01'));

        $relations->restore($uid);
        $this->assertCount(1, $relations->relatedTo($this->organizationUid, 'task', 'tsk_01'));
    }

    public function test_metadata_round_trips(): void
    {
        $relations = $this->repository();
        $relations->create($this->organizationUid, 'task', 'tsk_01', 'contact', 'ct_01', 'relates_to', metadata: ['note' => 'follow up']);

        $found = $relations->relatedTo($this->organizationUid, 'task', 'tsk_01');

        $this->assertSame(['note' => 'follow up'], $found[0]['metadata']);
    }
}
