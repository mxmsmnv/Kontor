<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Integration;

use Kontor\AI\Domain\PendingAIAction;
use Kontor\AI\Health\AIHealthCheck;
use Kontor\AI\Infrastructure\Persistence\PendingAIActionRepository;
use Kontor\AI\Migrations\Migration0001CreatePendingActionsTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\SDK\ValueObjects\Uid;

final class AIHealthCheckTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreatePendingActionsTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_ai_pending_actions', 'kontor_organizations', 'kontor_migrations'];
    }

    private function repository(): PendingAIActionRepository
    {
        return new PendingAIActionRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_ok_with_no_pending_actions(): void
    {
        $result = (new AIHealthCheck($this->repository()))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_ok_with_a_recently_created_pending_action(): void
    {
        $this->repository()->save(PendingAIAction::create($this->organizationUid, 'draft', [], []));

        $result = (new AIHealthCheck($this->repository()))->run();

        $this->assertSame('ok', $result->status);
    }

    public function test_warns_on_a_stale_pending_action(): void
    {
        // Built via the public constructor directly (rather than
        // create()) so the test can backdate createdAt without
        // reflection — the constructor is public for exactly this kind
        // of hydration/test-fixture use.
        $action = new PendingAIAction(
            Uid::generate(),
            $this->organizationUid,
            'draft',
            [],
            [],
            'pending',
            null,
            null,
            new \DateTimeImmutable('-10 days'),
            null,
        );

        $this->repository()->save($action);

        $result = (new AIHealthCheck($this->repository()))->run();

        $this->assertSame('warning', $result->status);
        $this->assertSame(1, $result->details['staleCount']);
    }
}
