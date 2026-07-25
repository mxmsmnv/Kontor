<?php

declare(strict_types=1);

namespace Kontor\SDK\Scaffolding;

/**
 * `make:report` (kontor.md Substage 7.4). Generates a
 * `ReportProviderInterface` (kontor.md#9.9) implementation skeleton,
 * following the shape of every hand-written provider so far (e.g.
 * `Kontor\CRM\Infrastructure\Reports\PipelineReportProvider`): a
 * `\PDO`/`OrganizationRepository`-backed constructor, `key()`/`title()`
 * fixed to the values passed in, `schema()` pre-filled from the given
 * field map, and an `execute()` stub left for the caller to fill in with
 * the report's real query — a generic ad-hoc query builder isn't
 * something `ReportProviderInterface` requires or this scaffolder can
 * infer.
 */
final class ReportScaffolder
{
    /**
     * @param array<string, string> $fields field key => scalar type (string|int|decimal|date|money)
     */
    public function __construct(
        private readonly string $namespace,
        private readonly string $className,
        private readonly string $key,
        private readonly string $title,
        private readonly array $fields = [],
    ) {
    }

    /**
     * @return array<string, string> relative path => file contents
     */
    public function generate(string $targetDir, bool $dryRun = false): array
    {
        $files = ["{$this->className}.php" => $this->providerFile()];

        if (!$dryRun) {
            foreach ($files as $relativePath => $contents) {
                $fullPath = rtrim($targetDir, '/').'/'.$relativePath;
                @mkdir(dirname($fullPath), 0777, recursive: true);
                file_put_contents($fullPath, $contents);
            }
        }

        return $files;
    }

    private function providerFile(): string
    {
        $fieldsExport = $this->fields === []
            ? "['result' => 'string']"
            : var_export($this->fields, true);

        return <<<PHP
        <?php

        declare(strict_types=1);

        namespace {$this->namespace};

        use Kontor\\Core\\Infrastructure\\Persistence\\OrganizationRepository;
        use Kontor\\SDK\\Contracts\\ReportProviderInterface;
        use Kontor\\SDK\\DTO\\ReportQuery;
        use Kontor\\SDK\\DTO\\ReportResult;
        use Kontor\\SDK\\DTO\\ReportSchema;

        final class {$this->className} implements ReportProviderInterface
        {
            public function __construct(
                private readonly \\PDO \$pdo,
                private readonly OrganizationRepository \$organizations,
            ) {
            }

            public function key(): string
            {
                return '{$this->key}';
            }

            public function title(): string
            {
                return '{$this->title}';
            }

            public function schema(): ReportSchema
            {
                return new ReportSchema(
                    fields: {$fieldsExport},
                );
            }

            public function execute(ReportQuery \$query): ReportResult
            {
                \$organizationId = \$this->organizations->internalIdOf(\$query->organizationId);

                // TODO: replace with this report's real query.
                return new ReportResult(rows: [], totals: []);
            }
        }

        PHP;
    }
}
