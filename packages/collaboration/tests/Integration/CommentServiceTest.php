<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Tests\Integration;

use Kontor\Collaboration\Application\CommentService;
use Kontor\Collaboration\Application\MentionParser;
use Kontor\Collaboration\Infrastructure\Persistence\CommentRepository;
use Kontor\Collaboration\Infrastructure\Persistence\FollowerRepository;
use Kontor\Collaboration\Infrastructure\Persistence\MentionRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class CommentServiceTest extends DatabaseTestCase
{
    private CommentService $comments;
    private MentionRepository $mentions;
    private FollowerRepository $followers;

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $commentRepository = new CommentRepository($this->pdo, $organizations);
        $this->mentions = new MentionRepository($this->pdo, $organizations);
        $this->followers = new FollowerRepository($this->pdo, $organizations);

        $this->comments = new CommentService($commentRepository, $this->mentions, $this->followers, new MentionParser());
    }

    public function test_posting_a_comment_auto_follows_the_entity_for_the_author(): void
    {
        $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'Looks good to me.', authorUserId: 5);

        $this->assertTrue($this->followers->isFollowing($this->organizationUid, 'crm_deal', 'deal_01', 5));
    }

    public function test_posting_a_comment_records_mentions_parsed_from_the_body(): void
    {
        $comment = $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'Thoughts, @9?', authorUserId: 5);

        $mentions = $this->mentions->forComment($comment->uid->toString());
        $this->assertCount(1, $mentions);
        $this->assertSame(9, $mentions[0]->mentionedUserId);
        $this->assertFalse($mentions[0]->isRead());
    }

    public function test_explicit_mentions_merge_with_parsed_ones_without_duplicates(): void
    {
        $comment = $this->comments->post(
            $this->organizationUid, 'crm_deal', 'deal_01', 'Thoughts, @9?', authorUserId: 5, mentionedUserIds: [9, 11],
        );

        $mentionedIds = array_map(fn ($m) => $m->mentionedUserId, $this->mentions->forComment($comment->uid->toString()));
        sort($mentionedIds);

        $this->assertSame([9, 11], $mentionedIds);
    }

    public function test_the_author_mentioning_themselves_does_not_create_a_self_mention(): void
    {
        $comment = $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'Note to self @5', authorUserId: 5);

        $this->assertCount(0, $this->mentions->forComment($comment->uid->toString()));
    }

    public function test_a_reply_carries_its_parent_uid(): void
    {
        $root = $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'Original comment', authorUserId: 5);
        $reply = $this->comments->post($this->organizationUid, 'crm_deal', 'deal_01', 'A reply', authorUserId: 6, parentUid: $root->uid->toString());

        $this->assertTrue($reply->isReply());
        $this->assertSame($root->uid->toString(), $reply->parentUid);
    }
}
