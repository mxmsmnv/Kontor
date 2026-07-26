<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Events\EventDispatcher;
use Kontor\Queue\Application\QueueWorker;
use Kontor\Queue\Health\QueueHealthCheck;
use Kontor\Queue\Infrastructure\Persistence\JobRepository;
use Kontor\Queue\Infrastructure\Queue;
use Kontor\Queue\JobRegistry;
use Kontor\Queue\Migrations\Migration0001CreateJobsTable;
use Kontor\SDK\Contracts\QueueInterface;

/**
 * KontorQueue bootstrap module (kontor.md Substage 2.1). Registers the
 * "queue" capability (Kontor\SDK\Contracts\QueueInterface) into Kontor
 * Core's CapabilityRegistry — components dispatch jobs through that
 * capability, never by depending on this module directly.
 */
class KontorQueue extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Queue',
            'summary' => 'Asynchronous and delayed jobs, retries, dead-letter queue, priorities and progress.',
            'version' => '005',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorQueue',
            'icon' => 'tasks',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-queue-view' => 'View Kontor queue jobs',
                'kontor-queue-manage' => 'Manage Kontor queue configuration',
                'kontor-queue-retry' => 'Manually retry dead-letter jobs',
                'kontor-queue-cancel' => 'Cancel pending Kontor queue jobs',
            ],
        ];
    }

    private ?JobRegistry $jobRegistry = null;
    private ?JobRepository $jobRepository = null;
    private ?Queue $queue = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $kontor->container()->get(CapabilityRegistry::class)->register(
            capability: 'queue',
            version: '1.0',
            contract: QueueInterface::class,
            implementation: $this->queue(),
            component: 'KontorQueue',
        );
    }

    public function jobRegistry(): JobRegistry
    {
        return $this->jobRegistry ??= new JobRegistry();
    }

    public function jobRepository(): JobRepository
    {
        return $this->jobRepository ??= new JobRepository($this->pdo());
    }

    public function queue(): Queue
    {
        if ($this->queue !== null) {
            return $this->queue;
        }

        $events = $this->wire()->modules->get('Kontor')->container()->get(EventDispatcher::class);

        return $this->queue = new Queue($this->jobRepository(), $events);
    }

    public function worker(): QueueWorker
    {
        $events = $this->wire()->modules->get('Kontor')->container()->get(EventDispatcher::class);

        return new QueueWorker($this->jobRepository(), $this->jobRegistry(), $events);
    }

    public function healthCheck(): QueueHealthCheck
    {
        return new QueueHealthCheck($this->jobRepository());
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
            new Migration0001CreateJobsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('queue', self::getModuleInfo()['version'], 'queue');
        $components->enable('queue');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data, so
     * kontor_jobs is left in place.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Queue module removed. Job history was kept intact.'));
    }
}
