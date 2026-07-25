<?php

namespace ProcessWire;

use Kontor\Collaboration\Application\CommentService;
use Kontor\Collaboration\Application\MentionParser;
use Kontor\Collaboration\Application\UnreadStateService;
use Kontor\Collaboration\Health\CollaborationHealthCheck;
use Kontor\Collaboration\Infrastructure\Persistence\CommentRepository;
use Kontor\Collaboration\Infrastructure\Persistence\FollowerRepository;
use Kontor\Collaboration\Infrastructure\Persistence\MentionRepository;
use Kontor\Collaboration\Infrastructure\Persistence\NoteRepository;
use Kontor\Collaboration\Infrastructure\Persistence\UnreadStateRepository;
use Kontor\Collaboration\Migrations\Migration0001CreateNotesTable;
use Kontor\Collaboration\Migrations\Migration0002CreateCommentsTable;
use Kontor\Collaboration\Migrations\Migration0003CreateMentionsTable;
use Kontor\Collaboration\Migrations\Migration0004CreateFollowersTable;
use Kontor\Collaboration\Migrations\Migration0005CreateUnreadStatesTable;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;

/**
 * KontorCollaboration bootstrap module (kontor.md Substage 5.2). Depends
 * only on kontor/core — notes/comments/followers/unread-states are all
 * polymorphic (entity_type/entity_uid), attachable to any entity from any
 * other component without a hard dependency on it.
 */
class KontorCollaboration extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Collaboration',
            'summary' => 'Notes, comments, mentions, followers, unread states.',
            'version' => '001',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorCollaboration',
            'icon' => 'comments-o',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-collaboration-note-view' => 'View notes',
                'kontor-collaboration-note-create' => 'Create notes',
                'kontor-collaboration-note-edit' => 'Edit notes',
                'kontor-collaboration-note-archive' => 'Archive notes',
                'kontor-collaboration-comment-view' => 'View comments',
                'kontor-collaboration-comment-create' => 'Create comments',
                'kontor-collaboration-comment-edit' => 'Edit comments',
                'kontor-collaboration-comment-archive' => 'Archive comments',
                'kontor-collaboration-follow-manage' => 'Follow or unfollow records',
            ],
        ];
    }

    private ?NoteRepository $noteRepository = null;
    private ?CommentRepository $commentRepository = null;
    private ?MentionRepository $mentionRepository = null;
    private ?FollowerRepository $followerRepository = null;
    private ?UnreadStateRepository $unreadStateRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorCollaboration', $language, $strings);
        }
    }

    public function noteRepository(): NoteRepository
    {
        return $this->noteRepository ??= new NoteRepository($this->pdo(), $this->organizations());
    }

    public function commentRepository(): CommentRepository
    {
        return $this->commentRepository ??= new CommentRepository($this->pdo(), $this->organizations());
    }

    public function mentionRepository(): MentionRepository
    {
        return $this->mentionRepository ??= new MentionRepository($this->pdo(), $this->organizations());
    }

    public function followerRepository(): FollowerRepository
    {
        return $this->followerRepository ??= new FollowerRepository($this->pdo(), $this->organizations());
    }

    public function unreadStateRepository(): UnreadStateRepository
    {
        return $this->unreadStateRepository ??= new UnreadStateRepository($this->pdo(), $this->organizations());
    }

    public function commentService(): CommentService
    {
        return new CommentService($this->commentRepository(), $this->mentionRepository(), $this->followerRepository(), new MentionParser());
    }

    public function unreadStateService(): UnreadStateService
    {
        return new UnreadStateService($this->commentRepository(), $this->unreadStateRepository());
    }

    public function healthCheck(): CollaborationHealthCheck
    {
        return new CollaborationHealthCheck($this->pdo());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateNotesTable(),
            new Migration0002CreateCommentsTable(),
            new Migration0003CreateMentionsTable(),
            new Migration0004CreateFollowersTable(),
            new Migration0005CreateUnreadStatesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('collaboration', self::getModuleInfo()['version'], 'collaboration');
        $components->enable('collaboration');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Collaboration module removed. Notes and comments were kept intact.'));
    }
}
