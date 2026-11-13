<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\CRMIntake\Application\CRMIntakeService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use PDO;
use ProcessWire\WirePermissionException;

trait ContactIntakeTools
{
    private function contactIntakeTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorCRMIntake')
            || !$this->wire()->modules->isInstalled('KontorContacts')) {
            return [];
        }

        return [
            $this->tool(
                'kontor_contact_fields_get',
                'Read Kontor contact intake fields',
                'List the configured contact intake fields and optionally read answers for an existing contact.',
                $this->mcpContactIntakeGet(...),
                'read',
                ['uid' => $this->uidSchema()],
            ),
            $this->tool(
                'kontor_contact_fields_update',
                'Fill Kontor contact intake fields',
                'Set specified intake answers on an existing contact. Omitted fields are preserved. Use null to clear an optional answer or preview=true to validate without saving.',
                $this->mcpContactIntakeUpdate(...),
                'draft',
                [
                    'uid' => $this->uidSchema(),
                    'answers' => $this->jsonDocumentSchema(16_384, 'JSON object with intake field keys and values.'),
                    'preview' => ['type' => 'boolean', 'description' => 'Validate the merged answers without saving.'],
                ],
                ['uid', 'answers'],
            ),
        ];
    }

    public function mcpContactIntakeGet(string $uid = ''): array
    {
        $this->requireContactIntakeActor();
        return $this->safe(function () use ($uid): array {
            [$service, $organization] = $this->contactIntakeContext();
            $result = ['fields' => $service->fieldsFor($organization, 'contact')];
            if ($uid !== '') {
                $this->bridge()->getRecord('contacts', $uid, '["uid"]');
                $result['answers'] = $service->valuesFor($organization, 'contact', $uid);
            }
            return $result;
        });
    }

    public function mcpContactIntakeUpdate(string $uid, string $answers, bool $preview = false): array
    {
        $actor = $this->requireContactIntakeActor();
        $this->requireMcpResource('contacts', 'edit');

        return $this->safe(function () use ($uid, $answers, $preview, $actor): array {
            $modules = $this->wire()->modules;
            $container = $modules->get('Kontor')->container();
            $database = $container->get(PDO::class);
            $ownsTransaction = !$preview && DatabaseConcurrency::beginWriteTransaction($database);
            try {
                [$service, $organization] = $this->contactIntakeContext();
                $contact = $modules->get('KontorContacts')->contactRepository()->find($uid, !$preview);
                if ($contact === null || $contact->organizationId !== $organization) {
                    throw new \InvalidArgumentException('The contact was not found.');
                }

                $result = $this->applyContactIntakeAnswers($service, $organization, $uid, $answers, $preview);
                if (!$preview) {
                    $organizationId = $container->get(OrganizationRepository::class)->internalIdOf($organization);
                    $container->get(AuditLogger::class)->record(
                        $organizationId, 'contacts', 'contact', $uid, 'updated', 'user', (string) $actor->id,
                        metadata: ['transport' => 'mcp', 'intake_fields' => $result['submitted_fields']],
                    );
                }
                if ($ownsTransaction) {
                    $database->commit();
                }
                return $result;
            } catch (\Throwable $error) {
                if ($ownsTransaction && $database->inTransaction()) {
                    $database->rollBack();
                }
                throw $error;
            }
        });
    }

    private function applyContactIntakeAnswers(
        CRMIntakeService $service,
        string $organization,
        string $uid,
        string $answers,
        bool $preview,
    ): array {
        $fields = array_column($service->fieldsFor($organization, 'contact'), null, 'key');
        try {
            $changes = strlen($answers) <= 16_384 ? json_decode($answers, false, 8, JSON_THROW_ON_ERROR) : null;
        } catch (\JsonException) {
            $changes = null;
        }
        if (!$changes instanceof \stdClass || (array) $changes === []) {
            throw new \InvalidArgumentException('Supply a non-empty JSON object of contact intake answers.');
        }

        $values = array_intersect_key($service->valuesFor($organization, 'contact', $uid), $fields);
        foreach ((array) $changes as $key => $value) {
            $field = $fields[$key] ?? null;
            if ($field === null) {
                throw new \InvalidArgumentException('An answer uses an unknown intake field key.');
            }
            $values[$key] = $this->normalizeContactIntakeAnswer($field['type'], $value);
        }

        $result = $preview
            ? $service->validateAnswers($organization, 'contact', $values)
            : $service->saveAnswers($organization, 'contact', $uid, $values)->answers;

        return [
            'uid' => $uid,
            'saved' => !$preview,
            'submitted_fields' => array_keys((array) $changes),
            'answers' => $result,
        ];
    }

    private function requireContactIntakeActor(): \ProcessWire\User
    {
        if (($actor = $this->mcpActor()) === null) {
            throw new WirePermissionException('An authenticated Kontor MCP client is required.');
        }
        $this->requireMcpResource('contacts', 'view');
        return $actor;
    }

    /** @return array{CRMIntakeService,string} */
    private function contactIntakeContext(): array
    {
        $modules = $this->wire()->modules;
        $organization = $modules->get('Kontor')->container()
            ->get(OrganizationRepository::class)->defaultOrganization('US', 'en', 'USD');
        return [$modules->get('KontorCRMIntake')->service(), $organization->uid->toString()];
    }

    private function normalizeContactIntakeAnswer(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        $sanitizer = $this->wire()->sanitizer;
        if ($type === 'multiselect') {
            if (!is_array($value) || array_filter($value, static fn ($item): bool => !is_string($item)) !== []) {
                throw new \InvalidArgumentException('A multi-select intake field requires an array of strings.');
            }
            return array_map(fn (string $item): string => $sanitizer->text($item), $value);
        }
        if (!is_scalar($value)) {
            throw new \InvalidArgumentException('This intake field requires a single value.');
        }
        return $type === 'textarea' ? $sanitizer->textarea((string) $value) : $sanitizer->text((string) $value);
    }
}
