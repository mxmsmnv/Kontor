<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeInterface;
use Kontor\Collaboration\Domain\Comment;
use Kontor\Collaboration\Domain\Note;
use Kontor\Tasks\Domain\Task;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait TaskCollaborationTools
{
    private function taskCollaborationTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorCollaboration')) {
            return [];
        }

        $uid = $this->uidSchema();
        $kind = ['type' => 'string', 'enum' => ['note', 'comment']];
        $key = $this->idempotencySchema();

        return [
            $this->tool('kontor_task_discussion_list', 'Read task notes or comments', 'List visible task notes or comments.',
                [$this, 'mcpKontorTaskDiscussionList'], 'read', [
                    'uid' => $uid, 'kind' => $kind,
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ], ['uid', 'kind']),
            $this->tool('kontor_task_discussion_post', 'Post a task note or comment',
                'Add a note or comment, optionally replying to a comment in the same task. Does not queue email.',
                [$this, 'mcpKontorTaskDiscussionPost'], 'draft', [
                    'uid' => $uid, 'kind' => $kind, 'idempotency_key' => $key,
                    'body' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 10000],
                    'parent_uid' => $uid,
                ], ['uid', 'kind', 'body', 'idempotency_key']),
            $this->tool('kontor_task_discussion_archive', 'Archive a task note or comment', 'Archive the specified discussion record after explicit review.',
                [$this, 'mcpKontorTaskDiscussionArchive'], 'draft', [
                    'uid' => $uid, 'kind' => $kind, 'record_uid' => $uid, 'idempotency_key' => $key,
                    'confirmation' => ['type' => 'string', 'const' => 'ARCHIVE_REVIEWED_TASK_DISCUSSION'],
                ], ['uid', 'kind', 'record_uid', 'idempotency_key', 'confirmation'], destructive: true),
            $this->tool('kontor_task_follow', 'Set your task follow preference', 'Follow or unfollow a task as the linked user.',
                [$this, 'mcpKontorTaskFollow'], 'draft', [
                    'uid' => $uid, 'following' => ['type' => 'boolean'], 'idempotency_key' => $key,
                ], ['uid', 'following', 'idempotency_key']),
        ];
    }

    public function mcpKontorTaskDiscussionList(string $uid, string $kind, int $offset = 0): array
    {
        [$tasks, $actor, $access, $organization] = $this->taskContext();
        $module = $this->taskCollaboration($actor, $kind, 'view');
        $this->requireVisibleMcpTask($tasks->taskRepository()->find($uid), $access, $organization);
        $repository = $kind === 'note' ? $module->noteRepository() : $module->commentRepository();
        $records = array_filter($repository->forEntity('task', $uid), fn (Note|Comment $record): bool =>
            $this->taskDiscussionRecordMatches($record, $organization, $uid));
        $items = array_map(static fn (Note|Comment $record): array => [
            'uid' => $record->uid->toString(),
            'body' => $record->body,
            'createdBy' => $record->createdBy,
            'createdAt' => $record->createdAt->format(DateTimeInterface::ATOM),
            'parentUid' => $record instanceof Comment ? $record->parentUid : null,
        ], array_slice($records, $offset, 51));

        return $this->mcpTaskPage($items, $offset);
    }

    public function mcpKontorTaskDiscussionPost(string $uid, string $kind, string $body, string $idempotency_key, ?string $parent_uid = null): array
    {
        [$tasks, $actor, $access, $organization] = $this->taskContext();
        $module = $this->taskCollaboration($actor, $kind, 'create');
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 10000 || ($kind === 'note' && $parent_uid !== null)) {
            throw new WireException('Provide discussion text and use reply targets only with comments.');
        }
        if ($parent_uid !== null) {
            $this->taskCollaboration($actor, 'comment', 'view');
        }

        return $this->writeMcpTask(
            $tasks, $actor, $access, $organization, $idempotency_key, 'discussion_post',
            ['uid' => $uid, 'kind' => $kind, 'body' => $body, 'parentUid' => $parent_uid],
            fn (): array => $tasks->taskRepository()->changeActiveTask($uid, function (Task $task) use (
                $module, $actor, $access, $organization, $uid, $kind, $body, $parent_uid,
            ): array {
                $this->requireVisibleMcpTask($task, $access, $organization);
                if ($kind === 'note') {
                    $record = Note::create($organization, 'task', $uid, $body, (int) $actor->id);
                    $module->noteRepository()->save($record);
                } else {
                    if ($parent_uid !== null && !$this->taskDiscussionRecordMatches($module->commentRepository()->find($parent_uid), $organization, $uid)) {
                        throw new Wire404Exception('Parent comment not found in the visible task.');
                    }
                    $record = $module->commentService()->post($organization, 'task', $uid, $body, (int) $actor->id, $parent_uid);
                }

                return ['taskUid' => $uid, 'recordUid' => $record->uid->toString()];
            }),
        );
    }

    public function mcpKontorTaskDiscussionArchive(string $uid, string $kind, string $record_uid, string $idempotency_key, string $confirmation): array
    {
        [$tasks, $actor, $access, $organization] = $this->taskContext();
        $module = $this->taskCollaboration($actor, $kind, 'archive');
        if ($confirmation !== 'ARCHIVE_REVIEWED_TASK_DISCUSSION') {
            throw new WireException('Explicit confirmation is required to archive a discussion record.');
        }
        $repository = $kind === 'note' ? $module->noteRepository() : $module->commentRepository();

        return $this->writeMcpTask(
            $tasks, $actor, $access, $organization, $idempotency_key, 'discussion_archive',
            ['uid' => $uid, 'kind' => $kind, 'recordUid' => $record_uid],
            fn (): array => $tasks->taskRepository()->changeActiveTask($uid, function (Task $task) use (
                $repository, $access, $organization, $uid, $record_uid,
            ): array {
                $this->requireVisibleMcpTask($task, $access, $organization);
                if (!$this->taskDiscussionRecordMatches($repository->find($record_uid), $organization, $uid)) {
                    throw new Wire404Exception('Discussion record not found in the visible task.');
                }
                $repository->archive($record_uid);

                return ['taskUid' => $uid];
            }),
        );
    }

    public function mcpKontorTaskFollow(string $uid, bool $following, string $idempotency_key): array
    {
        [$tasks, $actor, $access, $organization] = $this->taskContext();
        $followers = $this->taskCollaboration($actor, 'follow', 'manage')->followerRepository();

        return $this->writeMcpTask(
            $tasks, $actor, $access, $organization, $idempotency_key, 'discussion_follow',
            ['uid' => $uid, 'following' => $following],
            fn (): array => $tasks->taskRepository()->changeActiveTask($uid, function (Task $task) use (
                $followers, $actor, $access, $organization, $uid, $following,
            ): array {
                $this->requireVisibleMcpTask($task, $access, $organization);
                if ($following) {
                    $followers->follow($organization, 'task', $uid, (int) $actor->id);
                } else {
                    $followers->unfollow($organization, 'task', $uid, (int) $actor->id);
                }

                return ['taskUid' => $uid, 'following' => $following];
            }),
        );
    }

    private function taskCollaboration(User $actor, string $kind, string $action): \ProcessWire\KontorCollaboration
    {
        $modules = $this->wire()->modules;
        $allowedKinds = $action === 'manage' ? ['follow'] : ['note', 'comment'];
        if (!in_array($kind, $allowedKinds, true) || !$modules->isInstalled('KontorCollaboration')
            || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-collaboration-' . $kind . '-' . $action))) {
            throw new WirePermissionException('The linked user lacks the required task collaboration permission.');
        }

        return $modules->get('KontorCollaboration');
    }

    private function taskDiscussionRecordMatches(Note|Comment|null $record, string $organization, string $uid): bool
    {
        return $record !== null && $record->entityType === 'task' && hash_equals($organization, $record->organizationId)
            && hash_equals($uid, $record->entityUid);
    }
}
