<?php

declare(strict_types=1);

namespace Kontor\AI\Tests\Integration;

use Kontor\AI\Application\AIActionApprovalService;
use Kontor\AI\Application\AIGateway;
use Kontor\AI\Infrastructure\Persistence\PendingAIActionRepository;
use Kontor\AI\Infrastructure\Registry\AIProviderRegistry;
use Kontor\AI\Migrations\Migration0001CreatePendingActionsTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\SDK\Contracts\KontorAIProviderInterface;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\AIResponse;

/**
 * The sixth real consumer of `Kontor\Core\Testing\DatabaseTestCase`
 * outside `kontor/core`.
 */
final class AIActionApprovalServiceTest extends DatabaseTestCase
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

    private function provider(bool $requiresConfirmation): KontorAIProviderInterface
    {
        return new class($requiresConfirmation) implements KontorAIProviderInterface {
            public function __construct(private readonly bool $requiresConfirmation)
            {
            }

            public function supports(string $capability): bool
            {
                return true;
            }

            public function execute(AIRequest $request): AIResponse
            {
                return new AIResponse(success: true, output: ['result' => 'ok'], requiresConfirmation: $this->requiresConfirmation);
            }
        };
    }

    private function service(bool $requiresConfirmation): AIActionApprovalService
    {
        $registry = new AIProviderRegistry();
        $registry->register($this->provider($requiresConfirmation));

        return new AIActionApprovalService(new AIGateway($registry), new PendingAIActionRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    public function test_a_response_not_requiring_confirmation_completes_immediately(): void
    {
        $result = $this->service(false)->requestAndMaybeApprove(new AIRequest('summarize', $this->organizationUid, [], requiresConfirmation: false));

        $this->assertFalse($result->isPending);
        $this->assertNotNull($result->response);
        $this->assertNull($result->pendingAction);
    }

    public function test_a_response_requiring_confirmation_becomes_a_pending_action(): void
    {
        $result = $this->service(true)->requestAndMaybeApprove(new AIRequest('draft', $this->organizationUid, ['x' => 1]), requestedBy: 5);

        $this->assertTrue($result->isPending);
        $this->assertNull($result->response);
        $this->assertNotNull($result->pendingAction);
        $this->assertTrue($result->pendingAction->isPending());
        $this->assertSame(5, $result->pendingAction->requestedBy);
        $this->assertSame(['result' => 'ok'], $result->pendingAction->output);
    }

    public function test_approve_marks_the_pending_action_approved(): void
    {
        $service = $this->service(true);
        $result = $service->requestAndMaybeApprove(new AIRequest('draft', $this->organizationUid, []));

        $approved = $service->approve($result->pendingAction->uid->toString(), decidedBy: 9);

        $this->assertSame('approved', $approved->status);
        $this->assertSame(9, $approved->decidedBy);
    }

    public function test_reject_marks_the_pending_action_rejected(): void
    {
        $service = $this->service(true);
        $result = $service->requestAndMaybeApprove(new AIRequest('draft', $this->organizationUid, []));

        $rejected = $service->reject($result->pendingAction->uid->toString());

        $this->assertSame('rejected', $rejected->status);
    }
}
