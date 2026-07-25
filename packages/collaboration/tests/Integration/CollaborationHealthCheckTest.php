<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Integration;

use Kontor\Collaboration\Application\CommentService;
use Kontor\Collaboration\Application\MentionParser;
use Kontor\Collaboration\Health\CollaborationHealthCheck;
use Kontor\Collaboration\Infrastructure\Persistence\CommentRepository;
use Kontor\Collaboration\Infrastructure\Persistence\FollowerRepository;
use Kontor\Collaboration\Infrastructure\Persistence\MentionRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class CollaborationHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_counts(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $comments = new CommentService(
            new CommentRepository($this->pdo, $organizations),
            new MentionRepository($this->pdo, $organizations),
            new FollowerRepository($this->pdo, $organizations),
            new MentionParser(),
        );
        $comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'Thoughts, @9?', authorUserId: 5);

        $result = (new CollaborationHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['comments']);
        $this->assertSame(1, $result->details['unreadMentions']);
    }
}
