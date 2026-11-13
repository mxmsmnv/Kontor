<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeImmutable;
use InvalidArgumentException;
use Kontor\API\Application\IdempotencyKeyConflictException;
use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Domain\Lead;
use Kontor\CRMIntake\Application\CRMIntakeService;
use Kontor\SDK\ValueObjects\Money;
use PDO;
use ProcessWire\KontorCRM;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;
use stdClass;

trait CrmRecordTools
{
    private function crmRecordTools(): array
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorCRM')) {
            return [];
        }

        $tools = [];
        foreach (['lead', 'deal'] as $entity) {
            $fields = $entity === 'lead'
                ? 'priority, status (updates only), estimated_value_minor, next_action_at (YYYY-MM-DDTHH:MM)'
                : 'value_minor, probability, expected_close_date (YYYY-MM-DD)';
            $description = 'Fields: title, contact_uid, company_uid, source, description, currency, intake_answers, ' . $fields . '.';
            $properties = [
                'fields' => $this->jsonDocumentSchema(16_384, $description),
                'idempotency_key' => $this->idempotencySchema(),
                'preview' => ['type' => 'boolean', 'description' => 'Validate fields and return the proposed summary without saving.'],
            ];
            $createProperties = $properties;
            if ($entity === 'deal') {
                $createProperties['fields'] = $this->jsonDocumentSchema(
                    16_384,
                    $description . ' Also supply pipeline_uid and stage_uid for an open stage.',
                );
            }
            $tools[] = $this->tool(
                'kontor_crm_' . $entity . '_create',
                'Create CRM ' . $entity,
                'Create a CRM ' . $entity . ' in the Workspace organization. Supply title and configured required intake answers.',
                $entity === 'lead' ? $this->mcpCrmLeadCreate(...) : $this->mcpCrmDealCreate(...),
                'draft',
                $createProperties,
                ['fields', 'idempotency_key'],
            );
            $tools[] = $this->tool(
                'kontor_crm_' . $entity . '_update',
                'Update CRM ' . $entity,
                'Edit a CRM ' . $entity . ' by UID. Omitted fields are preserved. Use null to clear optional fields.',
                $entity === 'lead' ? $this->mcpCrmLeadUpdate(...) : $this->mcpCrmDealUpdate(...),
                'draft',
                ['uid' => $this->uidSchema()] + $properties,
                ['uid', 'fields', 'idempotency_key'],
            );
        }
        if ($modules->isInstalled('KontorCRMIntake')) {
            $tools[] = $this->tool(
                'kontor_crm_intake_get',
                'Get CRM intake fields',
                'Find configured lead or deal intake field keys, required fields and options. Provide a record UID to include saved answers.',
                $this->mcpCrmIntakeGet(...),
                'read',
                [
                    'entity' => ['type' => 'string', 'enum' => ['lead', 'deal']],
                    'uid' => $this->uidSchema(),
                ],
                ['entity'],
            );
        }
        return $tools;
    }

    public function mcpCrmLeadCreate(string $fields, string $idempotency_key, bool $preview = false): array
    {
        return $this->saveMcpCrmRecord('lead', null, $fields, $idempotency_key, $preview);
    }

    public function mcpCrmLeadUpdate(string $uid, string $fields, string $idempotency_key, bool $preview = false): array
    {
        return $this->saveMcpCrmRecord('lead', $uid, $fields, $idempotency_key, $preview);
    }

    public function mcpCrmDealCreate(string $fields, string $idempotency_key, bool $preview = false): array
    {
        return $this->saveMcpCrmRecord('deal', null, $fields, $idempotency_key, $preview);
    }

    public function mcpCrmDealUpdate(string $uid, string $fields, string $idempotency_key, bool $preview = false): array
    {
        return $this->saveMcpCrmRecord('deal', $uid, $fields, $idempotency_key, $preview);
    }

    public function mcpCrmIntakeGet(string $entity, ?string $uid = null): array
    {
        if (!in_array($entity, ['lead', 'deal'], true)) {
            throw new WireException('CRM intake supports lead and deal.');
        }
        $actor = $this->mcpActor();
        if ($actor === null) {
            throw new WirePermissionException('An authenticated Kontor MCP user is required.');
        }
        if ($uid === null) {
            $allowed = $this->mcpCrmCan($actor, 'kontor-crm-' . $entity . '-create')
                || $this->mcpCrmCan($actor, 'kontor-crm-' . $entity . '-edit');
        } else {
            $allowed = $this->mcpCrmCan($actor, $entity === 'lead' ? 'kontor-crm-lead-edit' : 'kontor-crm-deal-view');
        }
        if (!$allowed) {
            throw new WirePermissionException('The linked user cannot read these CRM intake fields.');
        }
        $crm = $this->mcpCrmModule();
        $service = $this->mcpCrmIntakeService();
        if ($service === null) {
            throw new Wire404Exception('Kontor CRM Intake is unavailable.');
        }
        $organizationUid = $this->mcpCrmOrganizationUid();
        $result = ['entity' => $entity, 'fields' => $service->fieldsFor($organizationUid, $entity)];
        if ($uid !== null) {
            $this->validateMcpCrmUid($uid);
            $repository = $entity === 'lead' ? $crm->leadRepository() : $crm->dealRepository();
            $this->requireMcpCrmOrganization($repository->find($uid), $organizationUid, ucfirst($entity));
            $result['answers'] = $service->valuesFor($organizationUid, $entity, $uid);
        }
        return $result;
    }

    private function saveMcpCrmRecord(string $entity, ?string $uid, string $document, string $key, bool $preview): array
    {
        $operation = $uid === null ? 'create' : 'edit';
        $actor = $this->mcpCrmActor('kontor-crm-' . $entity . '-' . $operation);
        if ($entity === 'deal' && $uid !== null && !$this->mcpCrmCan($actor, 'kontor-crm-deal-view')) {
            throw new WirePermissionException('The linked user cannot view CRM deals.');
        }
        if ($uid !== null) {
            $this->validateMcpCrmUid($uid);
        }
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('A valid idempotency key is required.');
        }
        $fields = $this->decodeMcpCrmFields($entity, $uid === null, $document);
        $crm = $this->mcpCrmModule();
        $repository = $entity === 'lead' ? $crm->leadRepository() : $crm->dealRepository();
        $organizationUid = $this->mcpCrmOrganizationUid();
        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $database = $container->get(PDO::class);
        $ownsTransaction = !$preview && DatabaseConcurrency::beginWriteTransaction($database);

        try {
            $existing = $uid === null ? null : $repository->find($uid, forUpdate: !$preview);
            if ($uid !== null) {
                $this->requireMcpCrmOrganization($existing, $organizationUid, ucfirst($entity));
            }
            foreach (['contact', 'company'] as $customer) {
                if (array_key_exists($customer . '_uid', $fields)
                    && !$this->mcpCrmCan($actor, 'kontor-contacts-' . $customer . '-view')) {
                    throw new WirePermissionException('The linked user cannot select this CRM customer type.');
                }
            }

            $apply = function () use ($crm, $actor, $container, $entity, $existing, $fields, $organizationUid, $preview, $repository): array {
                $record = $existing ?? $this->newMcpCrmRecord($crm, $organizationUid, $entity, $fields);
                $intake = $this->mcpCrmIntakeService();
                $answers = $this->prepareMcpCrmIntake($intake, $organizationUid, $entity, $existing?->uid->toString(), $fields);
                $this->fillMcpCrmRecord($record, $fields);
                $body = [
                    'entity' => $entity,
                    'uid' => $preview && $existing === null ? null : $record->uid->toString(),
                    'saved' => !$preview,
                    'fields' => array_keys($fields),
                    'title' => $record->title,
                    'status' => $record->status,
                ];
                if ($preview) {
                    $body['proposed'] = $this->mcpCrmProposedFields($record, $fields, $answers);
                } else {
                    $repository->save($record);
                    if ($answers !== null) {
                        $intake->saveAnswers($organizationUid, $entity, $body['uid'], $answers);
                    }
                    $container->get(AuditLogger::class)->record(
                        $container->get(OrganizationRepository::class)->internalIdOf($organizationUid),
                        'crm',
                        $entity,
                        $body['uid'],
                        $existing === null ? 'created' : 'updated',
                        'user',
                        (string) $actor->id,
                        current: ['title' => $record->title, 'status' => $record->status],
                        metadata: ['transport' => 'mcp', 'fields' => $body['fields']],
                    );
                }
                return ['status' => $existing === null ? 201 : 200, 'body' => $body];
            };

            if ($preview) {
                return $apply()['body'];
            }

            $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
            $response = $idempotency->remember(
                $organizationUid,
                'mcp:crm-record:' . $actor->id . ':' . hash('sha256', $key),
                ['entity' => $entity, 'uid' => $uid, 'fields' => $fields],
                $apply,
            );
            if ($ownsTransaction) {
                $database->commit();
            }
            return $response->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            if ($error instanceof InvalidArgumentException || $error instanceof IdempotencyKeyConflictException) {
                throw new WireException($error->getMessage());
            }
            if ($error instanceof WireException) {
                throw $error;
            }
            $this->wire()->log->save('kontor-mcp', 'CRM save failed: ' . get_class($error));
            throw new WireException('The CRM record could not be saved. Retry with the same idempotency key.');
        }
    }

    private function decodeMcpCrmFields(string $entity, bool $create, string $document): array
    {
        try {
            $data = strlen($document) <= 16_384 ? json_decode($document, false, 8, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            $data = null;
        }
        if (!$data instanceof stdClass || (array) $data === []) {
            throw new WireException('Supply a non-empty CRM field JSON object.');
        }
        $fields = (array) $data;
        $allowed = ['title', 'contact_uid', 'company_uid', 'source', 'description', 'currency', 'intake_answers'];
        if ($entity === 'lead') {
            $allowed = [...$allowed, 'priority', 'estimated_value_minor', 'next_action_at'];
            if (!$create) {
                $allowed[] = 'status';
            }
        } else {
            $allowed = [...$allowed, 'value_minor', 'probability', 'expected_close_date'];
            if ($create) {
                $allowed = [...$allowed, 'pipeline_uid', 'stage_uid'];
            }
        }
        if (array_diff(array_keys($fields), $allowed) !== []) {
            throw new WireException('The field document contains unsupported CRM fields.');
        }
        if (array_key_exists('intake_answers', $fields)) {
            if (!$fields['intake_answers'] instanceof stdClass) {
                throw new WireException('CRM intake answers must be a JSON object.');
            }
            $answers = (array) $fields['intake_answers'];
            ksort($answers);
            $fields['intake_answers'] = $answers;
        }

        ksort($fields);
        return $fields;
    }

    private function newMcpCrmRecord(KontorCRM $crm, string $organizationUid, string $entity, array $fields): Lead|Deal
    {
        $title = $this->mcpCrmRecordText($fields['title'] ?? null, 'title', 255);
        if ($title === null) {
            throw new WireException('A title is required.');
        }
        if ($entity === 'lead') {
            return Lead::create($organizationUid, $title);
        }

        $pipelineUid = $fields['pipeline_uid'] ?? null;
        $stageUid = $fields['stage_uid'] ?? null;
        if (!is_string($pipelineUid) || !is_string($stageUid)) {
            throw new WireException('A pipeline UID and stage UID are required.');
        }
        $this->mcpCrmPipeline($crm, $organizationUid, $pipelineUid);
        $this->validateMcpCrmUid($stageUid);
        $stage = $crm->stageRepository()->find($stageUid);
        if ($stage === null || $stage->pipelineUid !== $pipelineUid || $stage->stateType !== 'open') {
            throw new WireException('Select an open stage in the deal pipeline.');
        }

        return Deal::create($organizationUid, $pipelineUid, $stageUid, $title);
    }

    private function fillMcpCrmRecord(Lead|Deal $record, array $fields): void
    {
        foreach ($fields as $key => $value) {
            switch ($key) {
                case 'title':
                    $record->title = $this->mcpCrmRecordText($value, $key, 255)
                        ?? throw new WireException('A title is required.');
                    break;
                case 'source':
                    $record->source = $this->mcpCrmRecordText($value, $key, 50);
                    break;
                case 'description':
                    $record->description = $this->mcpCrmRecordText($value, $key, 8000, multiline: true);
                    break;
                case 'contact_uid':
                    $record->contactUid = $this->mcpCrmCustomerUid('contact', $record->organizationId, $value);
                    break;
                case 'company_uid':
                    $record->companyUid = $this->mcpCrmCustomerUid('company', $record->organizationId, $value);
                    break;
                case 'status':
                    $allowed = $record->isConverted() ? ['converted'] : ['new', 'contacted', 'qualified', 'lost'];
                    if (!in_array($value, $allowed, true)) {
                        throw new WireException('The lead status cannot be set to this value.');
                    }
                    $record->status = $value;
                    break;
                case 'priority':
                    if (!in_array($value, ['low', 'medium', 'high', 'urgent'], true)) {
                        throw new WireException('Select a valid lead priority.');
                    }
                    $record->priority = $value;
                    break;
                case 'next_action_at':
                    $record->nextActionAt = $this->mcpCrmRecordDate($value, 'Y-m-d\TH:i', $key);
                    break;
                case 'expected_close_date':
                    $record->expectedCloseDate = $this->mcpCrmRecordDate($value, 'Y-m-d', $key);
                    break;
                case 'probability':
                    if ($value !== null && (!is_int($value) || $value < 0 || $value > 100)) {
                        throw new WireException('Probability must be an integer from 0 to 100.');
                    }
                    $record->probability = $value;
                    break;
            }
        }
        $valueKey = $record instanceof Lead ? 'estimated_value_minor' : 'value_minor';
        if (array_key_exists($valueKey, $fields) || array_key_exists('currency', $fields)) {
            $current = $record instanceof Lead ? $record->estimatedValue : $record->value;
            $amount = array_key_exists($valueKey, $fields) ? $fields[$valueKey] : $current?->amountMinor();
            $currency = array_key_exists('currency', $fields) ? $fields['currency'] : $current?->currencyCode();
            if (($amount !== null && !is_int($amount))
                || ($currency !== null && (!is_string($currency) || preg_match('/^[A-Z]{3}$/D', $currency) !== 1))
                || ($amount !== null && $currency === null)) {
                throw new WireException('Use an integer amount in minor units and a three-letter uppercase currency.');
            }
            $money = $amount === null ? null : Money::ofMinor($amount, $currency);
            if ($record instanceof Lead) {
                $record->estimatedValue = $money;
            } else {
                $record->value = $money;
            }
        }
    }

    private function mcpCrmRecordText(mixed $value, string $field, int $limit, bool $multiline = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || mb_strlen($value) > $limit) {
            throw new WireException('Invalid CRM text field: ' . $field);
        }
        $sanitizer = $this->wire()->sanitizer;
        $clean = $multiline ? $sanitizer->textarea($value, ['maxLength' => $limit]) : $sanitizer->text($value, ['maxLength' => $limit]);
        return $clean === '' ? null : $clean;
    }

    private function mcpCrmProposedFields(Lead|Deal $record, array $fields, ?array $answers): array
    {
        $summary = $record instanceof Lead ? $this->mcpCrmLeadSummary($record) : $this->mcpCrmDealSummary($record);
        $proposed = $summary + [
            'contact_uid' => $record->contactUid,
            'company_uid' => $record->companyUid,
            'source' => $record->source,
            'description' => $record->description,
            'intake_answers' => $answers,
        ];
        if (isset($fields['intake_answers']) && $answers !== null) {
            $proposed['intake_answers'] = array_replace(
                array_fill_keys(array_keys($fields['intake_answers']), null),
                array_intersect_key($answers, $fields['intake_answers']),
            );
        }
        return array_intersect_key($proposed, $fields);
    }

    private function mcpCrmRecordDate(mixed $value, string $format, string $field): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!' . $format, $value) : false;
        if ($date === false || $date->format($format) !== $value) {
            throw new WireException('Invalid CRM date field: ' . $field);
        }
        return $date;
    }

    private function mcpCrmCustomerUid(string $customer, string $organizationUid, mixed $uid): ?string
    {
        if ($uid === null) {
            return null;
        }
        $modules = $this->wire()->modules;
        if (!is_string($uid) || !$modules->isInstalled('KontorContacts')) {
            throw new WireException('Select a valid CRM ' . $customer . '.');
        }
        $this->validateMcpCrmUid($uid);
        $contacts = $modules->get('KontorContacts');
        $repository = $customer === 'contact' ? $contacts->contactRepository() : $contacts->companyRepository();
        $record = $repository->find($uid);
        if ($record === null || $record->organizationId !== $organizationUid) {
            throw new Wire404Exception('The selected CRM ' . $customer . ' was not found.');
        }
        return $uid;
    }

    private function mcpCrmIntakeService(): ?CRMIntakeService
    {
        $modules = $this->wire()->modules;
        return $modules->isInstalled('KontorCRMIntake') ? $modules->get('KontorCRMIntake')->service() : null;
    }

    private function prepareMcpCrmIntake(
        ?CRMIntakeService $service,
        string $organizationUid,
        string $entity,
        ?string $uid,
        array &$fields,
    ): ?array {
        $schema = $service?->fieldsFor($organizationUid, $entity) ?? [];
        $submitted = $fields['intake_answers'] ?? [];
        if ($schema === []) {
            if ($submitted !== []) {
                throw new WireException('No CRM intake fields are configured for this record type.');
            }
            return null;
        }

        $byKey = array_column($schema, null, 'key');
        $answers = $uid === null ? [] : array_intersect_key($service->valuesFor($organizationUid, $entity, $uid), $byKey);
        foreach ($submitted as $key => $value) {
            $field = $byKey[$key] ?? null;
            if ($field === null) {
                throw new WireException('An unknown CRM intake field was submitted.');
            }
            if ($field['type'] === 'multiselect' && $value !== null) {
                if (!is_array($value) || !array_is_list($value) || count($value) > 100) {
                    throw new WireException('CRM multiselect answers must be a list of at most 100 option keys.');
                }
                $answers[$key] = array_map(fn (mixed $item): ?string => $this->mcpCrmRecordText($item, $key, 64), $value);
            } else {
                $answers[$key] = $this->mcpCrmRecordText($value, $key, $field['type'] === 'textarea' ? 8000 : 500, $field['type'] === 'textarea');
            }
        }
        $answers = $service->validateAnswers($organizationUid, $entity, $answers);
        foreach ($schema as $field) {
            if (($field['binding'] ?? null) !== 'source') {
                continue;
            }
            $value = $answers[$field['key']] ?? null;
            if (is_string($value) && $value !== '') {
                $fields['source'] = $value;
            }
            break;
        }

        return $answers;
    }
}
