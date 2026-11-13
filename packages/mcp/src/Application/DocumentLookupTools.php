<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Documents\Infrastructure\Persistence\TemplateRepository;
use ProcessWire\Wire404Exception;
use ProcessWire\WirePermissionException;

trait DocumentLookupTools
{
    private function mcpDocumentLookupTools(): array
    {
        if (!$this->wire()->modules->isInstalled('KontorDocuments')) {
            return [];
        }

        return [
            $this->tool('kontor_document_templates_list', 'List document templates',
                'List version and status metadata for organization document templates.',
                $this->mcpKontorDocumentTemplatesList(...), 'read', [
                    'document_type' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64, 'pattern' => '^[a-z0-9_-]+$'],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10000],
                ]),
            $this->tool('kontor_document_template_get', 'Get document template metadata',
                'Read version and status metadata for one organization document template.',
                $this->mcpKontorDocumentTemplateGet(...), 'read', ['uid' => $this->uidSchema()], ['uid']),
        ];
    }

    public function mcpKontorDocumentTemplatesList(?string $document_type = null, int $offset = 0): array
    {
        [$repository, $organization] = $this->mcpDocumentReadContext();
        $rows = $repository->templateMetadata($organization, type: $document_type, offset: $offset);

        return [
            'items' => array_slice($rows, 0, 25),
            'next_offset' => count($rows) > 25 && $offset + 25 <= 10000 ? $offset + 25 : null,
        ];
    }

    public function mcpKontorDocumentTemplateGet(string $uid): array
    {
        [$repository, $organization] = $this->mcpDocumentReadContext();
        $record = $repository->templateMetadata($organization, uid: $uid, limit: 1)[0] ?? null;
        if ($record === null) {
            throw new Wire404Exception('Document template not found.');
        }

        return ['record' => $record];
    }

    /** @return array{TemplateRepository, string} */
    private function mcpDocumentReadContext(): array
    {
        $actor = $this->mcpActor();
        $modules = $this->wire()->modules;
        if ($actor === null || (!$actor->isSuperuser() && !$actor->hasPermission('kontor-documents-template-view'))
            || !$modules->isInstalled('KontorDocuments')) {
            throw new WirePermissionException('The linked user cannot view document templates.');
        }
        $organization = $modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD')->uid->toString();

        return [$modules->get('KontorDocuments')->templateRepository(), $organization];
    }
}
