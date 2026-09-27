<?php

declare(strict_types=1);

namespace Kontor\CRM\Infrastructure\Reports;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\Contracts\ReportProviderInterface;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\ReportResult;
use Kontor\SDK\DTO\ReportSchema;

/**
 * kontor.md#9.9. Substage 3.3 "reports": deal count and total value
 * grouped by stage, restricted to a pipeline/status via $query->filters —
 * a fixed-shape "pipeline by stage" report rather than a fully generic
 * ad-hoc query builder, which ReportProviderInterface doesn't require.
 */
final class PipelineReportProvider implements ReportProviderInterface
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function key(): string
    {
        return 'crm_pipeline';
    }

    public function title(): string
    {
        return 'CRM Pipeline';
    }

    public function schema(): ReportSchema
    {
        return new ReportSchema(
            fields: ['stage_uid' => 'string', 'deal_count' => 'int', 'total_value_minor' => 'money'],
            filterableFields: ['pipeline_uid', 'status'],
            groupableFields: ['stage_uid'],
        );
    }

    public function execute(ReportQuery $query): ReportResult
    {
        $organizationId = $this->organizations->internalIdOf($query->organizationId);
        $conditions = ['organization_id = :organization_id'];
        $params = ['organization_id' => $organizationId];

        if (isset($query->filters['pipeline_uid'])) {
            $conditions[] = 'pipeline_uid = :pipeline_uid';
            $params['pipeline_uid'] = $query->filters['pipeline_uid'];
        }

        if (isset($query->filters['status'])) {
            $conditions[] = 'status = :status';
            $params['status'] = $query->filters['status'];
        }

        $where = implode(' AND ', $conditions);

        $statement = $this->pdo->prepare(
            "SELECT stage_uid, COUNT(*) AS deal_count, COALESCE(SUM(value_minor), 0) AS total_value_minor
             FROM kontor_crm_deals
             WHERE {$where}
             GROUP BY stage_uid"
        );
        $statement->execute($params);

        $rows = [];
        $totalCount = 0;
        $totalValue = 0;

        foreach ($statement->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $dealCount = (int) $row['deal_count'];
            $value = (int) $row['total_value_minor'];

            $rows[] = ['stage_uid' => $row['stage_uid'], 'deal_count' => $dealCount, 'total_value_minor' => $value];
            $totalCount += $dealCount;
            $totalValue += $value;
        }

        return new ReportResult($rows, ['deal_count' => $totalCount, 'total_value_minor' => $totalValue]);
    }
}
