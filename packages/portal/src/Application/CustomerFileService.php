<?php

declare(strict_types=1);

namespace Kontor\Portal\Application;

use Kontor\Files\Infrastructure\Persistence\FileRepository;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use RuntimeException;

/**
 * The "files" milestone: lists files attached to one of the customer's
 * own documents (reusing `kontor/files`'s own `FileRepository::forEntity()`
 * directly) and signs a time-limited download URL for one of them.
 *
 * Deliberately does NOT reuse `Kontor\Files\Application\FileManager::temporaryUrl()`
 * for the URL itself: that method signs against `KontorFiles`'s own
 * placeholder `baseUrl` (`kontor/files`'s README documents this — the
 * real verifying/streaming endpoint was explicitly left for "a later
 * stage"). This substage *is* that later stage, but the real endpoint
 * belongs to Portal, not to `kontor/files` (a customer, not an admin, is
 * downloading) — so this class signs its own URL, pointed at
 * `PortalFileDownloadHandler`'s real endpoint, using the same
 * `SignedUrlSigner` secret (ProcessWire's own `config->authSalt`) so the
 * signature it produces verifies correctly there. `kontor/files` itself
 * is never modified.
 */
final class CustomerFileService
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly SignedUrlSigner $signer,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>> raw file rows (kontor.md#11.6)
     */
    public function filesForDocument(string $documentType, string $documentUid): array
    {
        return $this->files->forEntity($documentType, $documentUid);
    }

    public function downloadUrl(string $fileUid, \DateTimeImmutable $expiresAt): string
    {
        $file = $this->files->find($fileUid) ?? throw new RuntimeException("File \"{$fileUid}\" was not found.");

        return $this->signer->sign($file['path'], $expiresAt);
    }
}
