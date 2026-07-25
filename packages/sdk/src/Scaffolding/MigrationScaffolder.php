<?php

declare(strict_types=1);

namespace Kontor\SDK\Scaffolding;

/**
 * `make:migration` (kontor.md Substage 7.4). Generates a numbered
 * migration file skeleton implementing `MigrationInterface`
 * (kontor.md#11.3), with a `CREATE TABLE` stub already following the
 * standard column conventions (kontor.md#10.3/10.4) so the only thing
 * left by hand is the component-specific columns.
 */
final class MigrationScaffolder
{
    public function __construct(
        private readonly string $namespace,
        private readonly string $component,
        private readonly int $number,
        private readonly string $migrationName,
        private readonly string $tableName,
    ) {
    }

    /**
     * @return array<string, string> relative path => file contents
     */
    public function generate(string $targetDir, bool $dryRun = false): array
    {
        $className = 'Migration'.str_pad((string) $this->number, 4, '0', STR_PAD_LEFT).$this->studly($this->migrationName);
        $files = ["{$className}.php" => $this->migrationFile($className)];

        if (!$dryRun) {
            foreach ($files as $relativePath => $contents) {
                $fullPath = rtrim($targetDir, '/').'/'.$relativePath;
                @mkdir(dirname($fullPath), 0777, recursive: true);
                file_put_contents($fullPath, $contents);
            }
        }

        return $files;
    }

    private function migrationFile(string $className): string
    {
        $paddedNumber = str_pad((string) $this->number, 4, '0', STR_PAD_LEFT);
        $snakeName = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $this->migrationName) ?? $this->migrationName);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$this->namespace};

        use Kontor\\Core\\Infrastructure\\Migrations\\MigrationInterface;

        final class {$className} implements MigrationInterface
        {
            public function component(): string
            {
                return '{$this->component}';
            }

            public function name(): string
            {
                return '{$paddedNumber}_{$snakeName}';
            }

            public function up(\\PDO \$pdo): void
            {
                \$pdo->exec(<<<SQL
                    CREATE TABLE IF NOT EXISTS {$this->tableName} (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        uid CHAR(26) NOT NULL,
                        organization_id BIGINT UNSIGNED NOT NULL,
                        name VARCHAR(255) NOT NULL,
                        status VARCHAR(20) NOT NULL DEFAULT 'active',
                        created_at DATETIME(6) NOT NULL,
                        updated_at DATETIME(6) NOT NULL,
                        created_by BIGINT UNSIGNED NULL,
                        version INT UNSIGNED NOT NULL DEFAULT 1,
                        archived_at DATETIME(6) NULL,
                        UNIQUE KEY uniq_uid (uid),
                        INDEX idx_organization_id (organization_id)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    SQL);
            }

            public function down(\\PDO \$pdo): void
            {
                \$pdo->exec('DROP TABLE IF EXISTS {$this->tableName}');
            }
        }

        PHP;
    }

    private function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $value)));
    }
}
