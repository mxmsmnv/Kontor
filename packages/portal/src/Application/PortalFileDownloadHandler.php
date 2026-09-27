<?php

declare(strict_types=1);

namespace Kontor\Portal\Application;

use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Portal\DTO\PortalDownloadResult;
use Kontor\SDK\Contracts\StorageInterface;

/**
 * The real HTTP-serving side of the "files" milestone: verifies a
 * `CustomerFileService::downloadUrl()`-signed request and streams the
 * file back — the exact endpoint `kontor/files`'s own README flags as
 * deferred ("wiring an actual HTTP endpoint... is an admin-route concern
 * for a later stage"). This substage is that later stage, and this is a
 * customer-facing download, not an admin one, so the endpoint lives here
 * rather than in `kontor/files` itself (which is never modified).
 *
 * Pure aside from the two calls it must make against real I/O
 * (`StorageInterface::exists()`/`read()`) — takes plain scalars in,
 * returns a plain DTO out, no superglobals — so it's unit-testable with a
 * fake `StorageInterface`. `KontorPortal::hookFileDownload()` is the thin
 * ProcessWire-glue translator, the same "pure core, thin I/O wrapper"
 * split `kontor/api`/`kontor/graphql` already use for their own real HTTP
 * entry points.
 */
final class PortalFileDownloadHandler
{
    public function __construct(
        private readonly SignedUrlSigner $signer,
        private readonly StorageInterface $storage,
    ) {
    }

    public function handle(string $path, int $expires, string $signature): PortalDownloadResult
    {
        if (!$this->signer->verify($path, $expires, $signature)) {
            return PortalDownloadResult::forbidden();
        }

        if (!$this->storage->exists($path)) {
            return PortalDownloadResult::notFound();
        }

        return PortalDownloadResult::found($this->storage->read($path), $this->guessMimeType($path));
    }

    private function guessMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}
