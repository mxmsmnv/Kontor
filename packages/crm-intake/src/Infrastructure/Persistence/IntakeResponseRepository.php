<?php

declare(strict_types=1);

namespace Kontor\CRMIntake\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\CRMIntake\Domain\IntakeResponse;
use Kontor\SDK\ValueObjects\Uid;

final class IntakeResponseRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(IntakeResponse $response): void
    {
        $now = $this->now();
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_crm_intake_responses
                (uid, organization_id, profile_uid, entity_type, entity_uid, answers_json, created_at, updated_at)
             VALUES
                (:uid, :organization_id, :profile_uid, :entity_type, :entity_uid, :answers_json, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                profile_uid = VALUES(profile_uid), answers_json = VALUES(answers_json), updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            'uid' => $response->uid->toString(),
            'organization_id' => $this->organizations->internalIdOf($response->organizationId),
            'profile_uid' => $response->profileUid,
            'entity_type' => $response->entityType,
            'entity_uid' => $response->entityUid,
            'answers_json' => json_encode($response->answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'created_at' => $response->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $now,
        ]);
        $response->updatedAt = new \DateTimeImmutable($now);
    }

    public function find(string $organizationUid, string $entityType, string $entityUid): ?IntakeResponse
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_crm_intake_responses
             WHERE organization_id = :organization_id AND entity_type = :entity_type AND entity_uid = :entity_uid'
        );
        $statement->execute([
            'organization_id' => $this->organizations->internalIdOf($organizationUid),
            'entity_type' => $entityType,
            'entity_uid' => $entityUid,
        ]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }
        $answers = json_decode((string) $row['answers_json'], true, 32, JSON_THROW_ON_ERROR);

        return new IntakeResponse(
            Uid::fromString((string) $row['uid']),
            $organizationUid,
            (string) $row['profile_uid'],
            (string) $row['entity_type'],
            (string) $row['entity_uid'],
            is_array($answers) ? $answers : [],
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at']),
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
