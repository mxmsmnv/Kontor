<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Integration;

use Kontor\Collaboration\Application\CommentService;
use Kontor\Collaboration\Application\MentionParser;
use Kontor\Collaboration\Application\UnreadStateService;
use Kontor\Collaboration\Infrastructure\Persistence\CommentRepository;
use Kontor\Collaboration\Infrastructure\Persistence\FollowerRepository;
use Kontor\Collaboration\Infrastructure\Persistence\MentionRepository;
use Kontor\Collaboration\Infrastructure\Persistence\UnreadStateRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class UnreadStateServiceTest extends DatabaseTestCase
{
    private CommentService $comments;
    private UnreadStateService $unread;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $commentRepository = new CommentRepository($this->pdo, $organizations);

        $this->comments = new CommentService(
            $commentRepository,
            new MentionRepository($this->pdo, $organizations),
            new FollowerRepository($this->pdo, $organizations),
            new MentionParser(),
        );
        $this->unread = new UnreadStateService($commentRepository, new UnreadStateRepository($this->pdo, $organizations));
    }

    public function test_a_thread_never_read_counts_every_comment_from_other_authors(): void
    {
        $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'First', authorUserId: 5);
        $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'Second', authorUserId: 6);

        $this->assertSame(2, $this->unread->unreadCount($this->organizationUid, 99, 'crm_deal', 'deal_01'));
    }

    public function test_own_comments_never_count_as_unread_for_their_author(): void
    {
        $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'From me', authorUserId: 5);

        $this->assertSame(0, $this->unread->unreadCount($this->organizationUid, 5, 'crm_deal', 'deal_01'));
    }

    public function test_marking_read_clears_the_count_and_new_comments_after_that_point_are_unread_again(): void
    {
        $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'Before read', authorUserId: 6);

        $this->unread->markRead($this->organizationUid, 99, 'crm_deal', 'deal_01');
        $this->assertSame(0, $this->unread->unreadCount($this->organizationUid, 99, 'crm_deal', 'deal_01'));

        $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'After read', authorUserId: 6);
        $this->assertSame(1, $this->unread->unreadCount($this->organizationUid, 99, 'crm_deal', 'deal_01'));
    }
}
