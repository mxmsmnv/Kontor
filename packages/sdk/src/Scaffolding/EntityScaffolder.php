<?php

declare(strict_types=1);

namespace Kontor\SDK\Scaffolding;

/**
 * `make:entity` (kontor.md Substage 7.4). Generates a Domain entity plus
 * a matching `RepositoryInterface` implementation, following the exact
 * shape every hand-written pair in this monorepo already uses (see
 * `Kontor\Entities\Domain\EntityDefinition` /
 * `Kontor\Entities\Infrastructure\Persistence\EntityDefinitionRepository`
 * for the pattern this reproduces): standard columns (kontor.md#10.3/10.4)
 * — `uid`, `organization_id`, `name`, `status`, `created_at`, `updated_at`,
 * `created_by`, `version`, `archived_at` — plus `find()`/`require()`/
 * `save()`/`archive()`/`restore()`. Extra scalar fields beyond the
 * standard set can be added by hand afterwards; this only scaffolds the
 * common baseline every entity shares.
 */
final class EntityScaffolder
{
    public function __construct(
        private readonly string $namespace,
        private readonly string $entityName,
        private readonly string $tableName,
    ) {
    }

    /**
     * @return array<string, string> relative path => file contents
     */
    public function generate(string $targetDir, bool $dryRun = false): array
    {
        $files = [
            "src/Domain/{$this->entityName}.php" => $this->domainFile(),
            "src/Infrastructure/Persistence/{$this->entityName}Repository.php" => $this->repositoryFile(),
        ];

        if (!$dryRun) {
            foreach ($files as $relativePath => $contents) {
                $fullPath = rtrim($targetDir, '/').'/'.$relativePath;
                @mkdir(dirname($fullPath), 0777, recursive: true);
                file_put_contents($fullPath, $contents);
            }
        }

        return $files;
    }

    private function domainFile(): string
    {
        $entity = $this->entityName;

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$this->namespace}\\Domain;

        use Kontor\\SDK\\ValueObjects\\Uid;

        final class {$entity}
        {
            public function __construct(
                public readonly Uid \$uid,
                public readonly string \$organizationId,
                public string \$name,
                public string \$status,
                public readonly \\DateTimeImmutable \$createdAt,
                public \\DateTimeImmutable \$updatedAt,
                public readonly ?int \$createdBy,
            ) {
            }

            public static function create(
                string \$organizationId,
                string \$name,
                ?int \$createdBy = null,
            ): self {
                \$now = new \\DateTimeImmutable();

                return new self(
                    uid: Uid::generate(),
                    organizationId: \$organizationId,
                    name: \$name,
                    status: 'active',
                    createdAt: \$now,
                    updatedAt: \$now,
                    createdBy: \$createdBy,
                );
            }

            public function isActive(): bool
            {
                return \$this->status === 'active';
            }
        }

        PHP;
    }

    private function repositoryFile(): string
    {
        $entity = $this->entityName;
        $table = $this->tableName;

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$this->namespace}\\Infrastructure\\Persistence;

        use InvalidArgumentException;
        use Kontor\\Core\\Infrastructure\\Persistence\\OrganizationRepository;
        use {$this->namespace}\\Domain\\{$entity};
        use Kontor\\SDK\\Contracts\\RepositoryInterface;
        use Kontor\\SDK\\ValueObjects\\Uid;
        use RuntimeException;

        final class {$entity}Repository implements RepositoryInterface
        {
            public function __construct(
                private readonly \\PDO \$pdo,
                private readonly OrganizationRepository \$organizations,
            ) {
            }

            public function find(string \$id): ?{$entity}
            {
                \$statement = \$this->pdo->prepare('SELECT * FROM {$table} WHERE uid = :uid');
                \$statement->execute(['uid' => \$id]);

                \$row = \$statement->fetch(\\PDO::FETCH_ASSOC);

                return \$row === false ? null : \$this->hydrate(\$row);
            }

            public function require(string \$id): {$entity}
            {
                return \$this->find(\$id) ?? throw new RuntimeException("{$entity} \\"{\$id}\\" was not found.");
            }

            public function save(object \$entity): void
            {
                if (!\$entity instanceof {$entity}) {
                    throw new InvalidArgumentException('{$entity}Repository::save() expects a {$entity}.');
                }

                \$organizationId = \$this->organizations->internalIdOf(\$entity->organizationId);

                \$statement = \$this->pdo->prepare(
                    'INSERT INTO {$table}
                        (uid, organization_id, name, status, created_at, updated_at, created_by, version)
                     VALUES
                        (:uid, :organization_id, :name, :status, :created_at, :updated_at, :created_by, 1)
                     ON DUPLICATE KEY UPDATE
                        name = VALUES(name), status = VALUES(status), updated_at = VALUES(updated_at), version = version + 1'
                );

                \$statement->execute([
                    'uid' => \$entity->uid->toString(),
                    'organization_id' => \$organizationId,
                    'name' => \$entity->name,
                    'status' => \$entity->status,
                    'created_at' => \$entity->createdAt->format('Y-m-d H:i:s.u'),
                    'updated_at' => \$entity->updatedAt->format('Y-m-d H:i:s.u'),
                    'created_by' => \$entity->createdBy,
                ]);
            }

            public function archive(string \$id): void
            {
                \$statement = \$this->pdo->prepare('UPDATE {$table} SET archived_at = :now WHERE uid = :uid');
                \$statement->execute(['now' => (new \\DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => \$id]);
            }

            public function restore(string \$id): void
            {
                \$statement = \$this->pdo->prepare('UPDATE {$table} SET archived_at = NULL WHERE uid = :uid');
                \$statement->execute(['uid' => \$id]);
            }

            /**
             * @return {$entity}[]
             */
            public function forOrganization(string \$organizationUid): array
            {
                \$organizationId = \$this->organizations->internalIdOf(\$organizationUid);

                \$statement = \$this->pdo->prepare('SELECT * FROM {$table} WHERE organization_id = :organization_id ORDER BY name ASC');
                \$statement->execute(['organization_id' => \$organizationId]);

                return array_map(\$this->hydrate(...), \$statement->fetchAll(\\PDO::FETCH_ASSOC));
            }

            private function hydrate(array \$row): {$entity}
            {
                return new {$entity}(
                    uid: Uid::fromString(\$row['uid']),
                    organizationId: \$this->organizationUidFor((int) \$row['organization_id']),
                    name: \$row['name'],
                    status: \$row['status'],
                    createdAt: new \\DateTimeImmutable(\$row['created_at']),
                    updatedAt: new \\DateTimeImmutable(\$row['updated_at']),
                    createdBy: \$row['created_by'] !== null ? (int) \$row['created_by'] : null,
                );
            }

            private function organizationUidFor(int \$organizationId): string
            {
                \$statement = \$this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
                \$statement->execute(['id' => \$organizationId]);

                return (string) \$statement->fetchColumn();
            }
        }

        PHP;
    }
}
