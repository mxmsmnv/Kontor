<?php

declare(strict_types=1);

namespace Kontor\Documents\Application;

use Kontor\Documents\Domain\DocumentTemplate;
use Kontor\Documents\Infrastructure\Persistence\TemplateRepository;
use Kontor\SDK\Contracts\EventDispatcherInterface;
use Kontor\SDK\Events\KontorEvent;

/**
 * Create/edit workflow for templates — the "templates" and "multilingual
 * output" milestones. Editing a template never mutates a row in place
 * (kontor.md#10.4's archived_at is reversible, not a place to overwrite
 * history that an already-issued document's template_uid may still point
 * at): publish() always inserts a new version and archives the previous
 * one, same pattern as kontor/files' FileManager::upload().
 */
final class TemplateManager
{
    public function __construct(
        private readonly TemplateRepository $templates,
        private readonly ?EventDispatcherInterface $events = null,
    ) {
    }

    public function publish(
        string $organizationUid,
        string $templateKey,
        string $documentType,
        string $language,
        string $name,
        string $bodyHtml,
        ?string $customCss = null,
        ?int $actorId = null,
    ): DocumentTemplate {
        $existing = $this->templates->findCurrentVersionExact($organizationUid, $templateKey, $language);
        $versionNumber = $existing !== null ? $existing->versionNumber + 1 : 1;

        $template = DocumentTemplate::create(
            organizationId: $organizationUid,
            templateKey: $templateKey,
            documentType: $documentType,
            language: $language,
            name: $name,
            bodyHtml: $bodyHtml,
            customCss: $customCss,
            versionNumber: $versionNumber,
            createdBy: $actorId,
        );

        $this->templates->save($template);

        if ($existing !== null) {
            $this->templates->archive($existing->uid->toString());
            $this->emit('document_template.version_published', $template, $organizationUid, ['supersedes' => $existing->uid->toString()]);
        } else {
            $this->emit('document_template.published', $template, $organizationUid, []);
        }

        return $template;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function emit(string $eventName, DocumentTemplate $template, string $organizationUid, array $data): void
    {
        $this->events?->dispatch(KontorEvent::create(
            event: $eventName,
            organizationId: $organizationUid,
            entityType: 'document_template',
            entityId: $template->uid->toString(),
            actorType: 'system',
            actorId: null,
            data: $data + ['templateKey' => $template->templateKey, 'language' => $template->language, 'version' => $template->versionNumber],
        ));
    }
}
