<?php

declare(strict_types=1);

namespace Kontor\Entities\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Entities\Domain\EntityField;
use Kontor\SDK\ValueObjects\Uid;

final class EntityFieldRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(EntityField $field): void
    {
        $organizationId = $this->organizations->internalIdOf($field->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_entity_fields (uid, organization_id, definition_uid, field_key, label, field_type, required, sort_order, created_at)
             VALUES (:uid, :organization_id, :definition_uid, :field_key, :label, :field_type, :required, :sort_order, :created_at)
             ON DUPLICATE KEY UPDATE label = VALUES(label), required = VALUES(required), sort_order = VALUES(sort_order)'
        );

        $statement->execute([
            'uid' => $field->uid->toString(),
            'organization_id' => $organizationId,
            'definition_uid' => $field->definitionUid,
            'field_key' => $field->fieldKey,
            'label' => $field->label,
            'field_type' => $field->fieldType,
            'required' => $field->required ? 1 : 0,
            'sort_order' => $field->sortOrder,
            'created_at' => $field->createdAt->format('Y-m-d H:i:s.u'),
        ]);
    }

    /**
     * @return EntityField[]
     */
    public function forDefinition(string $definitionUid): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_entity_fields WHERE definition_uid = :definition_uid ORDER BY sort_order ASC, id ASC');
        $statement->execute(['definition_uid' => $definitionUid]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): EntityField
    {
        return new EntityField(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            definitionUid: $row['definition_uid'],
            fieldKey: $row['field_key'],
            label: $row['label'],
            fieldType: $row['field_type'],
            required: (bool) $row['required'],
            sortOrder: (int) $row['sort_order'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
