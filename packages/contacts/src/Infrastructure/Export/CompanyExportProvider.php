<?php

declare(strict_types=1);

namespace Kontor\Contacts\Infrastructure\Export;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\ExportProviderInterface;
use Kontor\SDK\DTO\ExportContext;

final class CompanyExportProvider implements ExportProviderInterface
{
    private const ALL_FIELDS = [
        'uid', 'legal_name', 'trading_name', 'registration_number', 'tax_number',
        'vat_number', 'website', 'email', 'phone', 'status', 'created_at',
    ];

    private const ALLOWED_FILTERS = ['status', 'assigned_user_id'];

    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function entityType(): string
    {
        return 'company';
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

        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM kontor_companies WHERE {$where}");
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

        $statement = $this->pdo->prepare("SELECT {$columns} FROM kontor_companies WHERE {$where} ORDER BY id ASC");
        $statement->execute($params);

        while (($row = $statement->fetch(\PDO::FETCH_ASSOC)) !== false) {
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
