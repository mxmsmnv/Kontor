<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\CapabilityRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Files\Application\FileManager;
use Kontor\Files\Health\FilesHealthCheck;
use Kontor\Files\Infrastructure\Persistence\FileRepository;
use Kontor\Files\Infrastructure\Storage\LocalPrivateStorage;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Files\Migrations\Migration0001CreateFilesTable;
use Kontor\SDK\Contracts\StorageInterface;

/**
 * KontorFiles bootstrap module (kontor.md Substage 2.2). Registers the
 * "storage" capability (Kontor\SDK\Contracts\StorageInterface) into Kontor
 * Core's CapabilityRegistry — components store files through that
 * capability or through FileManager, never by depending on
 * LocalPrivateStorage directly.
 */
class KontorFiles extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Files',
            'summary' => 'Local private storage, file metadata, signed URLs and versions.',
            'version' => '007',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorFiles',
            'icon' => 'folder-open',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-files-file-view' => 'View Kontor file metadata',
                'kontor-files-file-upload' => 'Upload files into Kontor',
                'kontor-files-file-download' => 'Download Kontor files',
                'kontor-files-file-share' => 'Generate signed download links for Kontor files',
                'kontor-files-file-delete' => 'Archive Kontor files',
            ],
        ];
    }

    private ?StorageInterface $storage = null;
    private ?FileRepository $fileRepository = null;
    private ?SignedUrlSigner $signer = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $kontor->container()->get(CapabilityRegistry::class)->register(
            capability: 'storage',
            version: '1.0',
            contract: StorageInterface::class,
            implementation: $this->storage(),
            component: 'KontorFiles',
        );
    }

    public function storage(): StorageInterface
    {
        if ($this->storage !== null) {
            return $this->storage;
        }

        $secret = (string) (
            $this->wire()->config->tableSalt
            ?: $this->wire()->config->userAuthSalt
        );

        if ($secret === '') {
            throw new WireException($this->_(
                'Kontor Files requires ProcessWire tableSalt or userAuthSalt for signed download URLs.'
            ));
        }

        $this->signer = new SignedUrlSigner(
            secret: $secret,
            baseUrl: $this->wire()->config->urls->admin . 'kontor/files-download/',
        );

        return $this->storage = new LocalPrivateStorage(
            rootDir: $this->wire()->config->paths->assets . 'kontor/files',
            signer: $this->signer,
        );
    }

    public function verifyTemporaryUrl(string $path, int $expires, string $signature): bool
    {
        $this->storage();

        return $this->signer?->verify($path, $expires, $signature) ?? false;
    }

    public function fileRepository(): FileRepository
    {
        return $this->fileRepository ??= new FileRepository($this->pdo());
    }

    public function fileManager(): FileManager
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        $organizations = $kontor->container()->get(OrganizationRepository::class);
        $events = $kontor->container()->get(\Kontor\Core\Infrastructure\Events\EventDispatcher::class);

        return new FileManager($this->storage(), $this->fileRepository(), $organizations, $events);
    }

    public function healthCheck(): FilesHealthCheck
    {
        return new FilesHealthCheck($this->storage());
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
            new Migration0001CreateFilesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('files', self::getModuleInfo()['version'], 'files');
        $components->enable('files');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('files', self::getModuleInfo()['version'], 'files');
        $components->enable('files');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data, so
     * kontor_files rows and the files on disk are left in place.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Files module removed. Stored files and metadata were kept intact.'));
    }
}
