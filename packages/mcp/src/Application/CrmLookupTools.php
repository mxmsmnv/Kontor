<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;
use ProcessWire\KontorCRM;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait CrmLookupTools
{
    private function mcpCrmLookupTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorCRM')) {
            return [];
        }

        $pageSchema = [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
            'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
        ];
        $filterSchema = $pageSchema + [
            'query' => ['type' => 'string', 'maxLength' => 160],
            'archived' => ['type' => 'boolean'],
        ];
        $uidSchema = ['uid' => $this->uidSchema()];

        return [
            $this->tool(
                'kontor_crm_leads_list',
                'List CRM leads',
                'Search and page through leads in the Workspace organization. Set archived to true to list archived leads.',
                [$this, 'mcpKontorCrmLeadsList'],
                'read',
                $filterSchema + ['status' => ['type' => 'string', 'enum' => ['', 'new', 'contacted', 'qualified', 'converted', 'lost']]],
            ),
            $this->tool(
                'kontor_crm_lead_get',
                'Get CRM lead details',
                'Read the details of one lead by UID. Requires the same lead-edit permission as the Workspace lead form.',
                [$this, 'mcpKontorCrmLeadGet'],
                'read',
                $uidSchema,
                ['uid'],
            ),
            $this->tool(
                'kontor_crm_deals_list',
                'List CRM deals',
                'Search and page through deals in the Workspace organization. You can filter by pipeline UID and status.',
                [$this, 'mcpKontorCrmDealsList'],
                'read',
                $filterSchema + [
                    'pipeline_uid' => $this->uidSchema(),
                    'status' => ['type' => 'string', 'enum' => ['', 'open', 'won', 'lost']],
                ],
            ),
            $this->tool(
                'kontor_crm_deal_get',
                'Get CRM deal details',
                'Read the details of one deal by UID in the Workspace organization.',
                [$this, 'mcpKontorCrmDealGet'],
                'read',
                $uidSchema,
                ['uid'],
            ),
            $this->tool(
                'kontor_crm_pipelines_list',
                'List CRM pipelines',
                'Find pipeline UIDs in the Workspace organization.',
                [$this, 'mcpKontorCrmPipelinesList'],
                'read',
                $pageSchema,
            ),
            $this->tool(
                'kontor_crm_stages_list',
                'List CRM pipeline stages',
                'Find stage UIDs, order and state for a pipeline in the Workspace organization.',
                [$this, 'mcpKontorCrmStagesList'],
                'read',
                ['pipeline_uid' => $this->uidSchema()] + $pageSchema,
                ['pipeline_uid'],
            ),
        ];
    }

    public function mcpKontorCrmLeadsList(
        string $query = '',
        string $status = '',
        bool $archived = false,
        int $limit = 20,
        int $offset = 0,
    ): array {
        $this->mcpCrmActor('kontor-crm-lead-view');
        $this->validateMcpCrmPage($limit, $offset);
        $query = $this->validateMcpCrmQuery($query, $status, ['new', 'contacted', 'qualified', 'converted', 'lost']);
        $repository = $this->mcpCrmModule()->leadRepository();
        $organizationUid = $this->mcpCrmOrganizationUid();
        $status = $status === '' ? null : $status;
        $leads = $repository->findMatching($organizationUid, $query, $status, $archived, $limit, $offset);

        return [
            'items' => array_map($this->mcpCrmLeadSummary(...), $leads),
            'total' => $repository->countMatching($organizationUid, $query, $status, $archived),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function mcpKontorCrmLeadGet(string $uid): array
    {
        $actor = $this->mcpCrmActor('kontor-crm-lead-edit');
        $this->validateMcpCrmUid($uid);
        $lead = $this->mcpCrmModule()->leadRepository()->find($uid);
        $this->requireMcpCrmOrganization($lead, $this->mcpCrmOrganizationUid(), 'Lead');

        return $this->mcpCrmLeadSummary($lead) + [
            'description' => $lead->description,
            'assigned_user_id' => $lead->assignedUserId,
            'lost_reason' => $lead->lostReason,
            'contact_uid' => $this->mcpCrmCan($actor, 'kontor-contacts-contact-view') ? $lead->contactUid : null,
            'company_uid' => $this->mcpCrmCan($actor, 'kontor-contacts-company-view') ? $lead->companyUid : null,
            'converted_deal_uid' => $this->mcpCrmCan($actor, 'kontor-crm-deal-view') ? $lead->convertedDealUid : null,
        ];
    }

    public function mcpKontorCrmDealsList(
        string $query = '',
        string $status = '',
        ?string $pipeline_uid = null,
        bool $archived = false,
        int $limit = 20,
        int $offset = 0,
    ): array {
        $this->mcpCrmActor('kontor-crm-deal-view');
        $this->validateMcpCrmPage($limit, $offset);
        $query = $this->validateMcpCrmQuery($query, $status, ['open', 'won', 'lost']);
        $crm = $this->mcpCrmModule();
        $organizationUid = $this->mcpCrmOrganizationUid();
        if ($pipeline_uid !== null) {
            $this->mcpCrmPipeline($crm, $organizationUid, $pipeline_uid);
        }
        $repository = $crm->dealRepository();
        $status = $status === '' ? null : $status;
        $deals = $repository->findMatching($organizationUid, $pipeline_uid, $query, $status, $archived, $limit, $offset);

        return [
            'items' => array_map($this->mcpCrmDealSummary(...), $deals),
            'total' => $repository->countMatching($organizationUid, $pipeline_uid, $query, $status, $archived),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function mcpKontorCrmDealGet(string $uid): array
    {
        $actor = $this->mcpCrmActor('kontor-crm-deal-view');
        $this->validateMcpCrmUid($uid);
        $deal = $this->mcpCrmModule()->dealRepository()->find($uid);
        $this->requireMcpCrmOrganization($deal, $this->mcpCrmOrganizationUid(), 'Deal');

        return $this->mcpCrmDealSummary($deal) + [
            'description' => $deal->description,
            'assigned_user_id' => $deal->assignedUserId,
            'source' => $deal->source,
            'won_at' => $deal->wonAt?->format(DATE_ATOM),
            'lost_at' => $deal->lostAt?->format(DATE_ATOM),
            'lost_reason' => $deal->lostReason,
            'contact_uid' => $this->mcpCrmCan($actor, 'kontor-contacts-contact-view') ? $deal->contactUid : null,
            'company_uid' => $this->mcpCrmCan($actor, 'kontor-contacts-company-view') ? $deal->companyUid : null,
        ];
    }

    public function mcpKontorCrmPipelinesList(int $limit = 20, int $offset = 0): array
    {
        $this->mcpCrmActor('kontor-crm-deal-view');
        $this->validateMcpCrmPage($limit, $offset);
        $pipelines = $this->mcpCrmModule()->pipelineRepository()->forOrganization($this->mcpCrmOrganizationUid());

        return [
            'items' => array_map(static fn (Pipeline $pipeline): array => [
                'uid' => $pipeline->uid->toString(),
                'name' => $pipeline->name,
                'entity_type' => $pipeline->entityType,
                'is_default' => $pipeline->isDefault,
                'status' => $pipeline->status,
            ], array_slice($pipelines, $offset, $limit)),
            'total' => count($pipelines),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    public function mcpKontorCrmStagesList(string $pipeline_uid, int $limit = 20, int $offset = 0): array
    {
        $this->mcpCrmActor('kontor-crm-deal-view');
        $this->validateMcpCrmPage($limit, $offset);
        $crm = $this->mcpCrmModule();
        $this->mcpCrmPipeline($crm, $this->mcpCrmOrganizationUid(), $pipeline_uid);
        $stages = $crm->stageRepository()->forPipeline($pipeline_uid);

        return [
            'items' => array_map(static fn (Stage $stage): array => [
                'uid' => $stage->uid->toString(),
                'name' => $stage->displayNameIn('en') ?? $stage->nameKey,
                'name_key' => $stage->nameKey,
                'state' => $stage->stateType,
                'probability' => $stage->probability,
                'position' => $stage->sortOrder,
            ], array_slice($stages, $offset, $limit)),
            'total' => count($stages),
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    private function mcpCrmActor(string $permission): User
    {
        $actor = $this->mcpActor();
        if ($actor === null || !$this->mcpCrmCan($actor, $permission)) {
            throw new WirePermissionException('The linked user lacks the required Kontor CRM permission.');
        }

        return $actor;
    }

    private function mcpCrmCan(User $actor, string $permission): bool
    {
        return $actor->isSuperuser() || $actor->hasPermission($permission);
    }

    private function mcpCrmModule(): KontorCRM
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorCRM')) {
            throw new Wire404Exception('Kontor CRM is unavailable.');
        }

        return $modules->get('KontorCRM');
    }

    private function mcpCrmOrganizationUid(): string
    {
        return $this->wire()->modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();
    }

    private function mcpCrmPipeline(KontorCRM $crm, string $organizationUid, string $uid): void
    {
        $this->validateMcpCrmUid($uid);
        $this->requireMcpCrmOrganization($crm->pipelineRepository()->find($uid), $organizationUid, 'Pipeline');
    }

    private function requireMcpCrmOrganization(Lead|Deal|Pipeline|null $record, string $organizationUid, string $label): void
    {
        if ($record === null || $record->organizationId !== $organizationUid) {
            throw new Wire404Exception($label . ' was not found.');
        }
    }

    private function validateMcpCrmPage(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 50 || $offset < 0 || $offset > 10000) {
            throw new WireException('Invalid CRM page limit or offset.');
        }
    }

    private function validateMcpCrmQuery(string $query, string $status, array $allowedStatuses): string
    {
        if (mb_strlen($query) > 160 || ($status !== '' && !in_array($status, $allowedStatuses, true))) {
            throw new WireException('Invalid CRM query or status.');
        }

        return trim($query);
    }

    private function validateMcpCrmUid(string $uid): void
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('CRM UID must be a valid ULID.');
        }
    }

    private function mcpCrmLeadSummary(Lead $lead): array
    {
        return [
            'uid' => $lead->uid->toString(),
            'title' => $lead->title,
            'source' => $lead->source,
            'status' => $lead->status,
            'priority' => $lead->priority,
            'estimated_value_minor' => $lead->estimatedValue?->amountMinor(),
            'currency' => $lead->estimatedValue?->currencyCode(),
            'next_action_at' => $lead->nextActionAt?->format(DATE_ATOM),
        ];
    }

    private function mcpCrmDealSummary(Deal $deal): array
    {
        return [
            'uid' => $deal->uid->toString(),
            'pipeline_uid' => $deal->pipelineUid,
            'stage_uid' => $deal->stageUid,
            'title' => $deal->title,
            'status' => $deal->status,
            'value_minor' => $deal->value?->amountMinor(),
            'currency' => $deal->value?->currencyCode(),
            'probability' => $deal->probability,
            'expected_close_date' => $deal->expectedCloseDate?->format('Y-m-d'),
        ];
    }
}
