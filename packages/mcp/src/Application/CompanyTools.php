<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\API\Application\IdempotencyService;
use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PDO;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait CompanyTools
{
    private const COMPANY_TEXT_LIMITS = [
        'legalName' => 255, 'tradingName' => 255, 'registrationNumber' => 50,
        'email' => 255, 'phone' => 50, 'website' => 255, 'vatNumber' => 50, 'notes' => 16000,
    ];

    private function companyTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorContacts')) {
            return [];
        }

        $properties = [
            'status' => ['type' => 'string', 'enum' => ['active', 'inactive']],
            'customFields' => [
                'type' => 'object',
                'maxProperties' => 100,
                'propertyNames' => ['pattern' => '^[a-z0-9_]{1,128}$'],
                'additionalProperties' => ['type' => ['string', 'null'], 'maxLength' => 1000],
            ],
        ];
        foreach (self::COMPANY_TEXT_LIMITS as $field => $limit) {
            $properties[$field] = [
                'type' => $field === 'legalName' ? 'string' : ['string', 'null'],
                'maxLength' => $limit,
            ];
        }
        $fields = ['type' => 'object', 'properties' => $properties, 'additionalProperties' => false];
        $uid = $this->uidSchema();
        $key = $this->idempotencySchema();

        return [
            $this->tool('kontor_companies_list', 'List CRM companies',
                'Search customer and partner companies by name, contact details, or configured field values.',
                $this->mcpCompaniesList(...), 'read', [
                    'query' => ['type' => 'string', 'maxLength' => 150],
                    'status' => ['type' => 'string', 'enum' => ['', 'active', 'inactive']],
                    'archived' => ['type' => 'boolean'],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ]),
            $this->tool('kontor_company_get', 'Read a CRM company',
                'Read a company and its configured custom fields. Omit the UID to discover custom field definitions.',
                $this->mcpCompanyGet(...), 'read', ['uid' => $uid]),
            $this->tool('kontor_company_create', 'Create a CRM company',
                'Create one customer or partner company. Custom field keys come from company get.',
                $this->mcpCompanyCreate(...), 'draft', [
                    'fields' => $fields + ['required' => ['legalName']],
                    'idempotency_key' => $key,
                ], ['fields', 'idempotency_key']),
            $this->tool('kontor_company_update', 'Edit a CRM company',
                'Update the supplied company fields. Omitted values are preserved. Null clears an optional field.',
                $this->mcpCompanyUpdate(...), 'draft', [
                    'uid' => $uid,
                    'fields' => $fields + ['minProperties' => 1],
                    'idempotency_key' => $key,
                ], ['uid', 'fields', 'idempotency_key']),
            $this->tool('kontor_company_archive_action', 'Archive or restore a CRM company',
                'Archive or restore one company after the user confirms the action.',
                $this->mcpCompanyArchiveAction(...), 'draft', [
                    'uid' => $uid,
                    'action' => ['type' => 'string', 'enum' => ['archive', 'restore']],
                    'idempotency_key' => $key,
                    'confirmation' => ['type' => 'string', 'const' => 'APPLY_COMPANY_ARCHIVE_ACTION'],
                ], ['uid', 'action', 'idempotency_key', 'confirmation'], destructive: true),
        ];
    }

    public function mcpCompaniesList(
        string $query = '',
        string $status = '',
        bool $archived = false,
        int $limit = 20,
        int $offset = 0,
    ): array {
        $this->companyActor('view');
        if (mb_strlen($query) > 150 || !in_array($status, ['', 'active', 'inactive'], true)
            || $limit < 1 || $limit > 50 || $offset < 0 || $offset > 10000) {
            throw new WireException('Invalid company query or pagination.');
        }
        $organizationUid = $this->companyOrganization()->uid->toString();
        $repository = $this->companyRepo();
        $companies = $archived
            ? $repository->findArchived($organizationUid, $query, $limit, $offset, $status)
            : $repository->findAll($organizationUid, $query, $limit, $offset, $status);
        return [
            'items' => array_map(static fn (Company $company): array => [
                'uid' => $company->uid->toString(),
                'legalName' => $company->legalName,
                'tradingName' => $company->tradingName,
                'status' => $company->status,
            ], $companies),
            'total' => $repository->countMatching($organizationUid, $query, $archived, $status),
        ];
    }

    public function mcpCompanyGet(?string $uid = null): array
    {
        $this->companyActor('view');
        $organization = $this->companyOrganization();
        $definitions = $this->companyFieldDefinitions($organization);
        if ($uid === null) {
            return ['customFields' => $definitions];
        }

        $company = $this->mcpOwnedCompany($this->companyRepo(), $organization, $uid);
        $values = [];
        foreach (self::COMPANY_TEXT_LIMITS as $field => $limit) {
            $values[$field] = $company->{$field};
        }
        $stored = $company->metadata['fields'] ?? [];
        return [
            'company' => [
                'uid' => $uid, 'status' => $company->status,
                'customFields' => is_array($stored) ? array_intersect_key($stored, $definitions) : [],
            ] + $values,
            'customFields' => $definitions,
        ];
    }

    public function mcpCompanyCreate(array $fields, string $idempotency_key): array
    {
        $actor = $this->companyActor('create');
        $organization = $this->companyOrganization();
        $fields = $this->normalizeCompanyFields($fields, $organization, true);
        return $this->writeCompany($actor, $organization, 'created', null, $fields, $idempotency_key);
    }

    public function mcpCompanyUpdate(string $uid, array $fields, string $idempotency_key): array
    {
        $actor = $this->companyActor('edit');
        $organization = $this->companyOrganization();
        $fields = $this->normalizeCompanyFields($fields, $organization, false);
        return $this->writeCompany($actor, $organization, 'updated', $uid, $fields, $idempotency_key);
    }

    public function mcpCompanyArchiveAction(
        string $uid,
        string $action,
        string $idempotency_key,
        string $confirmation,
    ): array {
        $actor = $this->companyActor('archive');
        if ($confirmation !== 'APPLY_COMPANY_ARCHIVE_ACTION' || !in_array($action, ['archive', 'restore'], true)) {
            throw new WireException('Confirm archive or restore for this company.');
        }
        return $this->writeCompany(
            $actor,
            $this->companyOrganization(),
            $action === 'archive' ? 'archived' : 'restored',
            $uid,
            [],
            $idempotency_key,
        );
    }

    private function companyActor(string $action): User
    {
        $actor = $this->mcpActor();
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-contacts-company-' . $action))) {
            throw new WirePermissionException('The linked user cannot perform this company action.');
        }
        return $actor;
    }

    private function companyRepo(): CompanyRepository
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorContacts')) {
            throw new Wire404Exception('Kontor Contacts is unavailable.');
        }
        return $modules->get('KontorContacts')->companyRepository();
    }

    private function companyOrganization(): Organization
    {
        return $this->wire()->modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');
    }

    private function companyFieldDefinitions(Organization $organization): array
    {
        $fields = $organization->settings['companyFields'] ?? [];
        if (!is_array($fields)) {
            return [];
        }
        return array_filter($fields, static fn (mixed $label, mixed $key): bool =>
            is_string($label) && is_string($key) && preg_match('/^[a-z0-9_]{1,128}$/D', $key) === 1,
            ARRAY_FILTER_USE_BOTH);
    }

    private function mcpOwnedCompany(
        CompanyRepository $repository,
        Organization $organization,
        string $uid,
        bool $forUpdate = false,
    ): Company {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('Company UID must be a valid ULID.');
        }
        $company = $repository->find($uid, $forUpdate);
        if ($company === null || !hash_equals($organization->uid->toString(), $company->organizationId)) {
            throw new Wire404Exception('Company was not found.');
        }
        return $company;
    }

    private function normalizeCompanyFields(array $fields, Organization $organization, bool $create): array
    {
        if ($fields === [] || ($create && !isset($fields['legalName']))) {
            throw new WireException('Provide company fields and include legalName when creating a company.');
        }

        $cleaned = [];
        foreach ($fields as $key => $value) {
            if ($key === 'customFields') {
                $cleaned[$key] = $this->normalizeCompanyCustomFields($value, $organization);
                continue;
            }
            if ($key === 'status') {
                if (!in_array($value, ['active', 'inactive'], true)) {
                    throw new WireException('Company status must be active or inactive.');
                }
                $cleaned[$key] = $value;
                continue;
            }
            if (!isset(self::COMPANY_TEXT_LIMITS[$key]) || ($value !== null && !is_string($value))
                || ($value !== null && mb_strlen($value) > self::COMPANY_TEXT_LIMITS[$key])) {
                throw new WireException('Invalid or unsupported company field.');
            }
            $clean = $value === null ? null : trim(strip_tags($value));
            $clean = $clean === '' ? null : $clean;
            if ($key === 'legalName' && $clean === null) {
                throw new WireException('Company legal name is required.');
            }
            if ($key === 'email' && $clean !== null && filter_var($clean, FILTER_VALIDATE_EMAIL) === false) {
                throw new WireException('Company email is invalid.');
            }
            $cleaned[$key] = $clean;
        }
        ksort($cleaned);
        return $cleaned;
    }

    private function normalizeCompanyCustomFields(mixed $values, Organization $organization): array
    {
        if (!is_array($values) || count($values) > 100) {
            throw new WireException('Provide up to 100 custom company field values.');
        }

        $definitions = $this->companyFieldDefinitions($organization);
        $cleaned = [];
        foreach ($values as $key => $value) {
            if (!isset($definitions[$key]) || ($value !== null && (!is_string($value) || mb_strlen($value) > 1000))) {
                throw new WireException('Invalid or unknown custom company field.');
            }
            $value = $value === null ? null : trim(strip_tags($value));
            $cleaned[$key] = $value === '' ? null : $value;
        }
        ksort($cleaned);
        return $cleaned;
    }

    private function writeCompany(
        User $actor,
        Organization $organization,
        string $action,
        ?string $uid,
        array $fields,
        string $key,
    ): array {
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('Use an idempotency key of 8 to 160 safe characters.');
        }
        $modules = $this->wire()->modules;
        $container = $modules->get('Kontor')->container();
        $pdo = $container->get(PDO::class);
        $organizationUid = $organization->uid->toString();
        $repository = $this->companyRepo();
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $company = $uid === null ? null : $this->mcpOwnedCompany($repository, $organization, $uid, true);
            $response = $idempotency->remember(
                $organizationUid,
                'mcp:company:' . $actor->id . ':' . hash('sha256', $key),
                ['action' => $action, 'uid' => $uid, 'fields' => $fields],
                function () use ($repository, $company, $fields, $action, $container, $organizationUid, $actor): array {
                    $company ??= Company::create($organizationUid, $fields['legalName']);
                    $companyUid = $company->uid->toString();
                    if ($action === 'archived') {
                        $repository->archive($companyUid);
                    } elseif ($action === 'restored') {
                        $repository->restore($companyUid);
                    } else {
                        $this->applyCompanyFields($company, $fields);
                        $repository->save($company);
                    }
                    $container->get(AuditLogger::class)->record(
                        $container->get(OrganizationRepository::class)->internalIdOf($organizationUid),
                        'contacts', 'company', $companyUid, $action, 'user', (string) $actor->id,
                        metadata: ['transport' => 'mcp', 'changedFields' => array_keys($fields)],
                    );
                    return ['status' => 200, 'body' => ['uid' => $companyUid, 'action' => $action]];
                },
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $response->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof WireException) {
                throw $error;
            }
            return $this->safe(static fn () => throw $error);
        }
    }

    private function applyCompanyFields(Company $company, array $fields): void
    {
        $custom = $fields['customFields'] ?? [];
        unset($fields['customFields']);
        foreach ($fields as $key => $value) {
            $company->{$key} = $value;
        }
        if ($custom === []) {
            return;
        }
        $stored = $company->metadata['fields'] ?? [];
        $stored = is_array($stored) ? $stored : [];
        foreach ($custom as $key => $value) {
            if ($value === null) {
                unset($stored[$key]);
            } else {
                $stored[$key] = $value;
            }
        }
        if ($stored === []) {
            unset($company->metadata['fields']);
        } else {
            $company->metadata['fields'] = $stored;
        }
    }
}
