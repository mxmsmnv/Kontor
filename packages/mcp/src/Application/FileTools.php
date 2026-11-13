<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PDO;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait FileTools
{
    private const MCP_FILE_BYTES = 512 * 1024;

    private function fileTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorFiles')) {
            return [];
        }

        return [
            $this->tool(
                'kontor_file_upload',
                'Upload a small private Kontor file',
                'Store up to 512 KiB of base64-encoded bytes. Optionally attach the file to a visible task, contact, or company.',
                $this->mcpFileUpload(...),
                'draft',
                [
                    'filename' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'content_base64' => ['type' => 'string', 'minLength' => 4, 'maxLength' => 699052],
                    'idempotency_key' => $this->idempotencySchema(),
                    'classification' => ['type' => 'string', 'enum' => ['general', 'financial', 'confidential', 'restricted']],
                    'entity_type' => ['type' => 'string', 'enum' => ['task', 'contact', 'company']],
                    'entity_uid' => $this->uidSchema(),
                ],
                ['filename', 'content_base64', 'idempotency_key'],
            ),
            $this->tool(
                'kontor_file_archive_action',
                'Archive or restore a Kontor file',
                'Archive or restore a reviewed file in private storage after user confirmation.',
                $this->mcpFileArchiveAction(...),
                'draft',
                [
                    'uid' => $this->uidSchema(),
                    'action' => ['type' => 'string', 'enum' => ['archive', 'restore']],
                    'confirmation' => ['type' => 'string', 'const' => 'APPLY_REVIEWED_KONTOR_FILE_ACTION'],
                ],
                ['uid', 'action', 'confirmation'],
                destructive: true,
            ),
            $this->tool(
                'kontor_files_list',
                'List Kontor files',
                'Find visible files by name. Continue with next_offset even if a page has no visible items.',
                $this->mcpFilesList(...),
                'read',
                [
                    'query' => ['type' => 'string', 'maxLength' => 160],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ],
            ),
            $this->tool(
                'kontor_file_download',
                'Download a small Kontor file',
                'Read up to 512 KiB as base64 when the linked user may view and download the file.',
                $this->mcpFileDownload(...),
                'read',
                ['uid' => $this->uidSchema()],
                ['uid'],
            ),
            $this->tool(
                'kontor_file_get',
                'Read Kontor file metadata',
                'Read a file by UID, applying the linked user’s file and record permissions.',
                $this->mcpFileGet(...),
                'read',
                ['uid' => $this->uidSchema()],
                ['uid'],
            ),
        ];
    }

    public function mcpFilesList(string $query = '', int $offset = 0): array
    {
        $actor = $this->mcpActor();
        if ($actor === null || !$this->filePermission($actor, 'kontor-files-file-view')) {
            throw new WirePermissionException('The linked user cannot view files.');
        }
        $modules = $this->wire()->modules;
        $organizations = $modules->get('Kontor')->container()->get(OrganizationRepository::class);
        $uid = $organizations->defaultOrganization('US', 'en', 'USD')->uid->toString();
        $rows = $modules->get('KontorFiles')->fileRepository()->searchForOrganization(
            $organizations->internalIdOf($uid), $query, 51, $offset,
        );
        $items = [];
        foreach (array_slice($rows, 0, 50) as $row) {
            if ($this->mcpFileTargetVisible($actor, $row, $uid)) {
                $items[] = $this->mcpFileMetadata($row);
            }
        }

        return ['items' => $items, 'next_offset' => count($rows) > 50 && $offset + 50 <= 10000 ? $offset + 50 : null];
    }

    public function mcpFileGet(string $uid): array
    {
        [$file] = $this->mcpReadableFile($uid, 'kontor-files-file-view');

        return ['file' => $this->mcpFileMetadata($file)];
    }

    private function mcpFileMetadata(array $file): array
    {
        return array_intersect_key($file, array_flip([
            'uid', 'original_name', 'mime_type', 'size_bytes', 'visibility', 'classification',
            'entity_type', 'entity_uid', 'version_number', 'created_at', 'archived_at',
        ]));
    }

    public function mcpFileDownload(string $uid): array
    {
        [$file, $organizationUid] = $this->mcpReadableFile($uid, 'kontor-files-file-download');
        $maximum = self::MCP_FILE_BYTES;
        if ($file['archived_at'] !== null || (int) $file['size_bytes'] > $maximum) {
            throw new WireException('Download an active file of at most 512 KiB through MCP.');
        }
        return $this->safe(function () use ($uid, $file, $organizationUid, $maximum): array {
            $stream = $this->wire()->modules->get('KontorFiles')->fileManager()->read($uid, $organizationUid);
            try {
                $bytes = stream_get_contents($stream, $maximum + 1);
                if ($bytes === false || strlen($bytes) > $maximum) {
                    throw new WireException('The file exceeds the MCP response limit or could not be read.');
                }
            } finally {
                fclose($stream);
            }
            return [
                'uid' => $uid,
                'filename' => $file['original_name'],
                'mime_type' => $file['mime_type'],
                'content_base64' => base64_encode($bytes),
            ];
        });
    }

    public function mcpFileUpload(
        string $filename,
        string $content_base64,
        string $idempotency_key,
        string $classification = 'general',
        ?string $entity_type = null,
        ?string $entity_uid = null,
    ): array {
        $actor = $this->mcpActor();
        if ($actor === null || !$this->filePermission($actor, 'kontor-files-file-upload')) {
            throw new WirePermissionException('The linked user cannot upload files.');
        }
        if ($filename === '.' || $filename === '..' || trim($filename) === '' || strlen($filename) > 255
            || strpbrk($filename, "/\\") !== false || preg_match('/[[:cntrl:]]/', $filename)) {
            throw new WireException('Use a filename without a directory or control characters.');
        }
        if (($entity_type === null) !== ($entity_uid === null)
            || ($entity_type !== null && !in_array($entity_type, ['task', 'contact', 'company'], true))
            || !in_array($classification, ['general', 'financial', 'confidential', 'restricted'], true)
            || !preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $idempotency_key)) {
            throw new WireException('Supply a valid classification, optional attachment target, and idempotency key.');
        }
        $bytes = strlen($content_base64) <= 699052 ? base64_decode($content_base64, true) : false;
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MCP_FILE_BYTES
            || base64_encode($bytes) !== $content_base64) {
            throw new WireException('Supply between 1 byte and 512 KiB as standard padded base64.');
        }

        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $organizations = $container->get(OrganizationRepository::class);
        $organizationUid = $organizations->defaultOrganization('US', 'en', 'USD')->uid->toString();
        if (!$this->mcpFileTargetVisible($actor, ['entity_type' => $entity_type, 'entity_uid' => $entity_uid], $organizationUid)) {
            throw new Wire404Exception('The attachment target was not found.');
        }

        $files = $modules->get('KontorFiles');
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $request = [$filename, hash('sha256', $bytes), $classification, $entity_type, $entity_uid];
        $key = 'mcp:file-upload:' . $actor->id . ':' . hash('sha256', $idempotency_key);

        return $this->safe(function () use ($idempotency, $organizationUid, $actor, $key, $request, $files, $bytes, $container, $organizations): array {
            $database = $container->get(PDO::class);
            if (!DatabaseConcurrency::beginWriteTransaction($database)) {
                throw new WireException('A separate upload transaction is required.');
            }
            $path = null;
            try {
                $response = $idempotency->remember(
                    $organizationUid,
                    $key,
                    $request,
                    function () use ($files, $organizationUid, $actor, $request, $bytes, $container, $organizations, &$path): array {
                        [$filename, , $classification, $entityType, $entityUid] = $request;
                        $result = $files->fileManager()->upload(
                            $organizationUid, $filename, $bytes, 'private', $classification, $entityType, $entityUid,
                            actorId: (int) $actor->id,
                        );
                        $path = $files->fileRepository()->find($result['uid'])['path'] ?? null;
                        $container->get(AuditLogger::class)->record(
                            $organizations->internalIdOf($organizationUid), 'files', 'file', $result['uid'],
                            'uploaded', 'user', (string) $actor->id, metadata: ['transport' => 'mcp'],
                        );
                        return ['status' => 201, 'body' => $result];
                    },
                );
                $database->commit();
                return $response->body;
            } catch (\Throwable $error) {
                if ($database->inTransaction()) {
                    $database->rollBack();
                    if ($path !== null) {
                        try {
                            $files->storage()->delete($path);
                        } catch (\Throwable) {
                            $this->wire()->log->save('kontor-mcp', 'File upload rollback storage cleanup failed.');
                        }
                    }
                }
                throw $error;
            }
        });
    }

    public function mcpFileArchiveAction(string $uid, string $action, string $confirmation): array
    {
        if ($confirmation !== 'APPLY_REVIEWED_KONTOR_FILE_ACTION' || !in_array($action, ['archive', 'restore'], true)) {
            throw new WireException('Confirm the reviewed file action.');
        }
        [$file, $organizationUid, $actor] = $this->mcpReadableFile($uid, 'kontor-files-file-delete');
        if (($file['archived_at'] !== null) === ($action === 'archive')) {
            return ['uid' => $uid, 'archived' => $action === 'archive'];
        }
        return $this->safe(function () use ($uid, $action, $organizationUid, $actor, $file): array {
            $modules = $this->wire()->modules;
            $manager = $modules->get('KontorFiles')->fileManager();
            if ($action === 'archive') {
                $manager->archive($uid, $organizationUid);
            } else {
                $manager->restore($uid, $organizationUid);
            }
            $modules->get('Kontor')->container()->get(AuditLogger::class)->record(
                (int) $file['organization_id'], 'files', 'file', $uid, $action . 'd', 'user', (string) $actor->id,
                metadata: ['transport' => 'mcp'],
            );

            return ['uid' => $uid, 'archived' => $action === 'archive'];
        });
    }

    private function mcpReadableFile(string $uid, string $permission): array
    {
        $actor = $this->mcpActor();
        if ($actor === null || !$this->filePermission($actor, 'kontor-files-file-view')
            || !$this->filePermission($actor, $permission)) {
            throw new WirePermissionException('The linked user cannot view files.');
        }

        $modules = $this->wire()->modules;
        $organizations = $modules->get('Kontor')->container()->get(OrganizationRepository::class);
        $organization = $organizations->defaultOrganization('US', 'en', 'USD');
        $file = $modules->get('KontorFiles')->fileRepository()->find($uid);
        if ($file === null || (int) $file['organization_id'] !== $organizations->internalIdOf($organization->uid->toString())
            || !$this->mcpFileTargetVisible($actor, $file, $organization->uid->toString())) {
            throw new Wire404Exception('File not found.');
        }

        return [$file, $organization->uid->toString(), $actor];
    }

    private function filePermission(User $actor, string $permission): bool
    {
        return $actor->isSuperuser() || $actor->hasPermission($permission);
    }

    private function mcpFileTargetVisible(User $actor, array $file, string $organizationUid): bool
    {
        $type = (string) ($file['entity_type'] ?? '');
        if ($type === '') {
            return true;
        }
        $uid = (string) ($file['entity_uid'] ?? '');
        $modules = $this->wire()->modules;
        if ($type === 'task') {
            if (!$modules->isInstalled('KontorTasks')) {
                return false;
            }
            $tasks = $modules->get('KontorTasks');
            $task = $tasks->taskRepository()->find($uid);
            return $task !== null && $task->organizationId === $organizationUid
                && $tasks->accessFor($actor)->allows($task);
        }

        if (in_array($type, ['contact', 'company'], true)) {
            if (!$modules->isInstalled('KontorContacts')
                || !$this->filePermission($actor, 'kontor-contacts-' . $type . '-view')) {
                return false;
            }
            $contacts = $modules->get('KontorContacts');
            $repository = $type === 'contact' ? $contacts->contactRepository() : $contacts->companyRepository();
            return $repository->find($uid)?->organizationId === $organizationUid;
        }

        return $actor->isSuperuser();
    }
}
