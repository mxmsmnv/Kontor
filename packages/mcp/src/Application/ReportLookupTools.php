<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ReportQuery;
use ProcessWire\KontorReports;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WirePermissionException;

trait ReportLookupTools
{
    private const MCP_REPORT_PERMISSIONS = ['crm_pipeline' => 'kontor-crm-deal-view'];

    private function mcpReportLookupTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorReports')) {
            return [];
        }

        return [
            $this->tool('kontor_reports_list', 'List available Kontor reports', 'List supported report providers and their filter and group fields.',
                $this->mcpKontorReportsList(...)),
            $this->tool('kontor_report_run', 'Run a Kontor report', 'Read bounded CRM pipeline summaries using the linked user’s report and CRM permissions.',
                $this->mcpKontorReportRun(...), 'read', [
                    'provider' => ['type' => 'string', 'enum' => array_keys(self::MCP_REPORT_PERMISSIONS)],
                    'pipeline_uid' => $this->uidSchema(),
                    'status' => ['type' => 'string', 'enum' => ['open', 'won', 'lost']],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ], ['provider']),
        ];
    }

    public function mcpKontorReportsList(): array
    {
        [$module, $actor] = $this->mcpReportReadContext();
        $items = [];
        foreach (self::MCP_REPORT_PERMISSIONS as $key => $permission) {
            if (!$module->providerRegistry()->has($key) || (!$actor->isSuperuser() && !$actor->hasPermission($permission))) {
                continue;
            }
            $provider = $module->providerRegistry()->get($key);
            $schema = $provider->schema();
            $items[] = [
                'key' => $key,
                'title' => $provider->title(),
                'fields' => $schema->fields,
                'filter_fields' => $schema->filterableFields,
                'group_fields' => $schema->groupableFields,
            ];
        }

        return ['items' => $items];
    }

    public function mcpKontorReportRun(string $provider, ?string $pipeline_uid = null, ?string $status = null, int $offset = 0): array
    {
        [$module, $actor] = $this->mcpReportReadContext();
        $permission = self::MCP_REPORT_PERMISSIONS[$provider] ?? null;
        if ($permission === null || !$module->providerRegistry()->has($provider)
            || (!$actor->isSuperuser() && !$actor->hasPermission($permission))) {
            throw new Wire404Exception('Report not found.');
        }
        $modules = $this->wire()->modules;
        $organization = $modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();
        if ($pipeline_uid !== null) {
            $pipeline = $modules->get('KontorCRM')->pipelineRepository()->find($pipeline_uid);
            if ($pipeline === null || !hash_equals($organization, $pipeline->organizationId)) {
                throw new Wire404Exception('Pipeline not found.');
            }
        }
        $filters = array_filter(['pipeline_uid' => $pipeline_uid, 'status' => $status], static fn (?string $value): bool => $value !== null);
        $result = $module->reportBuilder()->run($provider, new ReportQuery($organization, $filters));
        $rows = $result->rows;
        usort($rows, static fn (array $left, array $right): int => strcmp($left['stage_uid'], $right['stage_uid']));

        return [
            'rows' => array_slice($rows, $offset, 50),
            'totals' => $result->totals,
            'next_offset' => count($rows) > $offset + 50 && $offset + 50 <= 10000 ? $offset + 50 : null,
        ];
    }

    /** @return array{KontorReports, User} */
    private function mcpReportReadContext(): array
    {
        $actor = $this->mcpActor();
        $modules = $this->wire()->modules;
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-reports-report-view'))
            || !$modules->isInstalled('KontorReports')) {
            throw new WirePermissionException('The linked user cannot view organization reports.');
        }

        return [$modules->get('KontorReports'), $actor];
    }
}
