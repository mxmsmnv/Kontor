<?php

declare(strict_types=1);

namespace Kontor\Contacts\Infrastructure\Export;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\ExportProviderInterface;
use Kontor\SDK\DTO\ExportContext;

/**
 * kontor.md#9.7. $fields is whitelisted against fields() before being
 * interpolated into SQL — the interface anticipates caller-selected sparse
 * fieldsets (spec section 20.7), which could otherwise become a SQL
 * injection vector once an API layer passes user input through to it.
 */
final class ContactExportProvider implements ExportProviderInterface
{
    private const ALL_FIELDS = [
        'uid', 'type', 'first_name', 'middle_name', 'last_name', 'display_name',
        'email', 'phone', 'mobile', 'job_title', 'status', 'source', 'created_at',
    ];

    private const ALLOWED_FILTERS = ['status', 'type', 'assigned_user_id'];

    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function entityType(): string
    {
        return 'contact';
    }

    public function fields(): array
    {
        return self::ALL_FIELDS;
    }

    public function filters(): array
    {
        return self::ALLOWED_FILTERS;
    }

    public function count(array $filters, ExportContext $context): int
    {
        [$where, $params] = $this->buildWhere($filters, $context);

        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM kontor_contacts WHERE {$where}");
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function iterate(array $filters, array $fields, ExportContext $context): iterable
    {
        $selectedFields = $fields === [] ? self::ALL_FIELDS : array_values(array_intersect($fields, self::ALL_FIELDS));

        if ($selectedFields === []) {
            $selectedFields = self::ALL_FIELDS;
        }

        [$where, $params] = $this->buildWhere($filters, $context);
        $columns = implode(', ', $selectedFields);

        $statement = $this->pdo->prepare("SELECT {$columns} FROM kontor_contacts WHERE {$where} ORDER BY id ASC");
        $statement->execute($params);

        foreach ($statement as $row) {
            yield $row;
        }
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function buildWhere(array $filters, ExportContext $context): array
    {
        $organizationId = $this->organizations->internalIdOf($context->organizationId);
        $conditions = ['organization_id = :organization_id', 'deleted_at IS NULL'];
        $params = ['organization_id' => $organizationId];

        foreach (self::ALLOWED_FILTERS as $filterKey) {
            if (isset($filters[$filterKey])) {
                $conditions[] = "{$filterKey} = :filter_{$filterKey}";
                $params["filter_{$filterKey}"] = $filters[$filterKey];
            }
        }

        return [implode(' AND ', $conditions), $params];
    }
}
