<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Events\EventDispatcher;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Mail\Application\EntityLinkingService;
use Kontor\Mail\Application\InboundMailService;
use Kontor\Mail\Application\MailboxService;
use Kontor\Mail\Application\MailEventEmitter;
use Kontor\Mail\Application\OutboundMailService;
use Kontor\Mail\Contracts\MailSenderInterface;
use Kontor\Mail\Health\MailHealthCheck;
use Kontor\Mail\Infrastructure\Adapters\RawEmailForwardAdapter;
use Kontor\Mail\Infrastructure\Mail\NativeMailSender;
use Kontor\Mail\Infrastructure\Persistence\MailboxRepository;
use Kontor\Mail\Infrastructure\Persistence\MailMessageRepository;
use Kontor\Mail\Infrastructure\Registry\InboundMailAdapterRegistry;
use Kontor\Mail\Migrations\Migration0001CreateMailboxesTable;
use Kontor\Mail\Migrations\Migration0002CreateMessagesTable;

/**
 * KontorMail bootstrap module (kontor.md Substage 9.1, first component
 * of Stage 9 — Advanced capabilities). Depends only on kontor/core —
 * "entity linking" reuses Core's own RelationRepository directly rather
 * than a parallel table, the same choice kontor/tasks and kontor/entities
 * already made for their own "relations" milestones. Publishes real
 * events (mail.sent/mail.delivery_failed/mail.received) onto Core's
 * event bus so kontor/automation has real new triggers to react to.
 */
class KontorMail extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Mail',
            'summary' => 'Outbound history, inbound adapters, entity linking, shared mailboxes.',
            'version' => '100',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorMail',
            'icon' => 'envelope',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-mail-mailbox-manage' => 'Create and manage shared mailboxes',
                'kontor-mail-message-view' => 'View mail messages',
                'kontor-mail-message-send' => 'Send outbound mail',
            ],
        ];
    }

    private ?MailboxRepository $mailboxRepository = null;
    private ?MailMessageRepository $messageRepository = null;
    private ?InboundMailAdapterRegistry $inboundAdapterRegistry = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
        $this->inboundAdapterRegistry()->register(new RawEmailForwardAdapter());
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__."/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorMail', $language, $strings);
        }
    }

    public function mailboxRepository(): MailboxRepository
    {
        return $this->mailboxRepository ??= new MailboxRepository($this->pdo(), $this->organizations());
    }

    public function messageRepository(): MailMessageRepository
    {
        return $this->messageRepository ??= new MailMessageRepository($this->pdo(), $this->organizations());
    }

    public function inboundAdapterRegistry(): InboundMailAdapterRegistry
    {
        return $this->inboundAdapterRegistry ??= new InboundMailAdapterRegistry();
    }

    public function mailboxes(): MailboxService
    {
        return new MailboxService($this->mailboxRepository());
    }

    public function outbound(): OutboundMailService
    {
        return new OutboundMailService(new NativeMailSender(), $this->messageRepository(), new MailEventEmitter($this->eventDispatcher()));
    }

    public function outboundWithSender(MailSenderInterface $sender): OutboundMailService
    {
        return new OutboundMailService(
            $sender,
            $this->messageRepository(),
            new MailEventEmitter($this->eventDispatcher()),
        );
    }

    public function inbound(): InboundMailService
    {
        return new InboundMailService($this->messageRepository(), new MailEventEmitter($this->eventDispatcher()));
    }

    public function entityLinking(): EntityLinkingService
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return new EntityLinkingService($kontor->container()->get(RelationRepository::class));
    }

    public function healthCheck(): MailHealthCheck
    {
        return new MailHealthCheck($this->pdo());
    }

    private function eventDispatcher(): EventDispatcher
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(EventDispatcher::class);
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateMailboxesTable(),
            new Migration0002CreateMessagesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('mail', self::getModuleInfo()['version'], 'mail');
        $components->enable('mail');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('mail', self::getModuleInfo()['version'], 'mail');
        $components->enable('mail');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Mail module removed. Mailboxes and messages were kept intact.'));
    }
}
