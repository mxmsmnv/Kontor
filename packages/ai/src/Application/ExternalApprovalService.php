<?php

declare(strict_types=1);

namespace Kontor\AI\Application;

use Kontor\AI\Domain\PendingAIAction;
use Kontor\AI\Infrastructure\Persistence\PendingAIActionRepository;
use RuntimeException;

final class ExternalApprovalService
{
    public function __construct(private readonly \PDO $pdo, private readonly PendingAIActionRepository $pending) {}

    public function submit(string $provider, string $externalId, string $organizationUid, array $metadata, ?int $requestedBy): PendingAIAction
    {
        $provider = strtolower(trim($provider));
        $externalId = strtolower(trim($externalId));
        if(!preg_match('/^[a-z][a-z0-9_.-]{1,31}$/', $provider)) throw new RuntimeException('Invalid external approval provider.');
        if(!preg_match('/^[a-f0-9-]{16,64}$/', $externalId)) throw new RuntimeException('Invalid external approval ID.');
        $existing = $this->find($provider, $externalId);
        if($existing !== null) return $existing;
        $safe = $this->redactedMetadata($metadata);
        $safe['provider'] = $provider;
        $safe['external_reference'] = $externalId;

        $this->pdo->beginTransaction();
        try {
            $action = PendingAIAction::create($organizationUid, $provider . '.links.confirm', $safe, ['decision' => 'withheld'], $requestedBy);
            $this->pending->save($action);
            $statement = $this->pdo->prepare('INSERT INTO kontor_ai_external_approvals (provider, external_id, pending_uid, created_at) VALUES (:provider, :external_id, :pending_uid, :created_at)');
            $statement->execute([
                'provider' => $provider,
                'external_id' => $externalId,
                'pending_uid' => $action->uid->toString(),
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
            ]);
            $this->pdo->commit();
            return $action;
        } catch(\Throwable $error) {
            if($this->pdo->inTransaction()) $this->pdo->rollBack();
            $existing = $this->find($provider, $externalId);
            if($existing !== null) return $existing;
            throw $error;
        }
    }

    public function find(string $provider, string $externalId): ?PendingAIAction
    {
        $statement = $this->pdo->prepare('SELECT pending_uid FROM kontor_ai_external_approvals WHERE provider=:provider AND external_id=:external_id');
        $statement->execute(['provider' => strtolower(trim($provider)), 'external_id' => strtolower(trim($externalId))]);
        $uid = $statement->fetchColumn();
        return is_string($uid) && $uid !== '' ? $this->pending->find($uid) : null;
    }

    public function referenceForPending(string $pendingUid): ?array
    {
        $statement = $this->pdo->prepare('SELECT provider, external_id FROM kontor_ai_external_approvals WHERE pending_uid=:pending_uid');
        $statement->execute(['pending_uid' => $pendingUid]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        return $row ? ['provider' => (string) $row['provider'], 'external_id' => (string) $row['external_id']] : null;
    }

    private function redactedMetadata(array $metadata): array
    {
        $host = strtolower(trim((string) ($metadata['host'] ?? '')));
        $folder = trim((string) ($metadata['folder'] ?? ''));
        $uid = max(0, (int) ($metadata['uid'] ?? 0));
        $accountId = max(0, (int) ($metadata['account_id'] ?? 0));
        if($host === '' || strlen($host) > 253 || !preg_match('/^[a-z0-9.-]+$/', $host)) throw new RuntimeException('Invalid external approval host.');
        if($folder === '' || strlen($folder) > 255 || preg_match('/[<>\r\n\0]/', $folder) || $uid < 1 || $accountId < 1) throw new RuntimeException('Invalid external approval metadata.');
        return ['host' => $host, 'folder' => $folder, 'uid' => $uid, 'account_id' => $accountId, 'redacted' => true];
    }
}
