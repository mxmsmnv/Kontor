<?php

declare(strict_types=1);

namespace Kontor\Collaboration\Application;

use Kontor\Collaboration\Domain\Comment;
use Kontor\Collaboration\Domain\Mention;
use Kontor\Collaboration\Infrastructure\Persistence\CommentRepository;
use Kontor\Collaboration\Infrastructure\Persistence\FollowerRepository;
use Kontor\Collaboration\Infrastructure\Persistence\MentionRepository;

/**
 * Ties the "comments", "mentions" and "followers" milestones together:
 * posting a comment extracts @mentions from its body (plus any explicitly
 * given), records one Mention per unique mentioned user (never the author
 * mentioning themselves), and auto-follows the entity for the comment's
 * author — the common "you're now watching this thread because you
 * replied to it" behavior.
 */
final class CommentService
{
    public function __construct(
        private readonly CommentRepository $comments,
        private readonly MentionRepository $mentions,
        private readonly FollowerRepository $followers,
        private readonly MentionParser $mentionParser,
    ) {
    }

    /**
     * @param int[] $mentionedUserIds explicit mentions, merged with any parsed from $body
     */
    public function post(
        string $organizationUid,
        string $entityType,
        string $entityUid,
        string $body,
        ?int $authorUserId = null,
        ?string $parentUid = null,
        array $mentionedUserIds = [],
    ): Comment {
        $comment = Comment::create($organizationUid, $entityType, $entityUid, $body, $parentUid, $authorUserId);
        $this->comments->save($comment);

        $allMentioned = array_unique([...$mentionedUserIds, ...$this->mentionParser->extract($body)]);

        foreach ($allMentioned as $userId) {
            if ($userId === $authorUserId) {
                continue;
            }

            $this->mentions->save(Mention::create($organizationUid, $comment->uid->toString(), $userId));
        }

        if ($authorUserId !== null) {
            $this->followers->follow($organizationUid, $entityType, $entityUid, $authorUserId);
        }

        return $comment;
    }
}
