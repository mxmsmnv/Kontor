<?php

declare(strict_types=1);

namespace Kontor\Tasks\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Tasks\Application\TaskRelationService;
use Kontor\Tasks\Domain\Task;
use Kontor\Tasks\Infrastructure\Persistence\TaskRepository;

final class TaskRelationServiceTest extends DatabaseTestCase
{
    private TaskRelationService $relations;
    private string $taskUid;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $tasks = new TaskRepository($this->pdo, $organizations);
        $this->relations = new TaskRelationService(new RelationRepository($this->pdo, $organizations));

        $task = Task::create($this->organizationUid, 'Follow up with lead');
        $tasks->save($task);
        $this->taskUid = $task->uid->toString();
    }

    public function test_link_to_entity_then_related_entities_round_trips(): void
    {
        $this->relations->linkToEntity($this->organizationUid, $this->taskUid, 'contact', 'ct_01');

        $related = $this->relations->relatedEntities($this->organizationUid, $this->taskUid);

        $this->assertCount(1, $related);
        $this->assertSame('contact', $related[0]['targetType']);
        $this->assertSame('ct_01', $related[0]['targetUid']);
    }

    public function test_tasks_related_to_an_entity(): void
    {
        $this->relations->linkToEntity($this->organizationUid, $this->taskUid, 'contact', 'ct_01');

        $taskUids = $this->relations->tasksRelatedTo($this->organizationUid, 'contact', 'ct_01');

        $this->assertSame([$this->taskUid], $taskUids);
    }
}
