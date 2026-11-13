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
 * The portal uses its own signer and HTTP endpoint so admin download links
 * and customer download links remain scoped to their respective routes.
 */
final class CustomerFileService
{
    public function __construct(
        private readonly FileRepository $files,
        private readonly SignedUrlSigner $signer,
        private readonly int $organizationId,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>> raw file rows (kontor.md#11.6)
     */
    public function filesForDocument(string $documentType, string $documentUid): array
    {
        if (strtolower($documentType) === 'task') {
            return [];
        }

        return $this->files->forEntity($documentType, $documentUid, $this->organizationId);
    }

    public function downloadUrl(string $fileUid, \DateTimeImmutable $expiresAt): string
    {
        $file = $this->files->find($fileUid) ?? throw new RuntimeException("File \"{$fileUid}\" was not found.");
        if ((int) $file['organization_id'] !== $this->organizationId || $file['archived_at'] !== null
            || strtolower((string) $file['entity_type']) === 'task') {
            throw new RuntimeException("File \"{$fileUid}\" was not found.");
        }

        return $this->signer->sign($file['path'], $expiresAt);
    }
}
