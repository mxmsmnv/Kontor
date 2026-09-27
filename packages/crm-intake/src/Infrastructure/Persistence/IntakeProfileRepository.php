<?php

declare(strict_types=1);

namespace Kontor\CRMIntake\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\CRMIntake\Domain\IntakeProfile;
use Kontor\SDK\ValueObjects\Uid;

final class IntakeProfileRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(IntakeProfile $profile): void
    {
        $organizationId = $this->organizations->internalIdOf($profile->organizationId);
        $now = $this->now();
        if ($profile->isDefault) {
            $statement = $this->pdo->prepare(
                'UPDATE kontor_crm_intake_profiles SET is_default = 0 WHERE organization_id = :organization_id'
            );
            $statement->execute(['organization_id' => $organizationId]);
        }
        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_crm_intake_profiles
                (uid, organization_id, name, fields_json, is_default, status, created_at, updated_at, created_by)
             VALUES
                (:uid, :organization_id, :name, :fields_json, :is_default, :status, :created_at, :updated_at, :created_by)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), fields_json = VALUES(fields_json), is_default = VALUES(is_default),
                status = VALUES(status), updated_at = VALUES(updated_at)'
        );
        $statement->execute([
            'uid' => $profile->uid->toString(),
            'organization_id' => $organizationId,
            'name' => $profile->name,
            'fields_json' => json_encode($profile->fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'is_default' => $profile->isDefault ? 1 : 0,
            'status' => $profile->status,
            'created_at' => $profile->createdAt->format('Y-m-d H:i:s.u'),
            'updated_at' => $now,
            'created_by' => $profile->createdBy,
        ]);
        $profile->updatedAt = new \DateTimeImmutable($now);
    }

    public function defaultForOrganization(string $organizationUid): ?IntakeProfile
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM kontor_crm_intake_profiles
             WHERE organization_id = :organization_id AND is_default = 1 AND status = 'active'
             ORDER BY updated_at DESC LIMIT 1"
        );
        $statement->execute(['organization_id' => $this->organizations->internalIdOf($organizationUid)]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row, $organizationUid);
    }

    /** @return IntakeProfile[] */
    public function forOrganization(string $organizationUid): array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_crm_intake_profiles WHERE organization_id = :organization_id ORDER BY is_default DESC, name'
        );
        $statement->execute(['organization_id' => $this->organizations->internalIdOf($organizationUid)]);

        return array_map(
            fn (array $row): IntakeProfile => $this->hydrate($row, $organizationUid),
            $statement->fetchAll(\PDO::FETCH_ASSOC),
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row, string $organizationUid): IntakeProfile
    {
        $fields = json_decode((string) $row['fields_json'], true, 32, JSON_THROW_ON_ERROR);

        return new IntakeProfile(
            Uid::fromString((string) $row['uid']),
            $organizationUid,
            (string) $row['name'],
            is_array($fields) ? $fields : [],
            (bool) $row['is_default'],
            (string) $row['status'],
            new \DateTimeImmutable((string) $row['created_at']),
            new \DateTimeImmutable((string) $row['updated_at']),
            $row['created_by'] !== null ? (int) $row['created_by'] : null,
        );
    }

    private function now(): string
    {
        return (new \DateTimeImmutable())->format('Y-m-d H:i:s.u');
    }
}
