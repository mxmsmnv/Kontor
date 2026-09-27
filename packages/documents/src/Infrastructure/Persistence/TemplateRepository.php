<?php

declare(strict_types=1);

namespace Kontor\Documents\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Documents\Domain\DocumentTemplate;
use Kontor\SDK\ValueObjects\Uid;

/**
 * PDO-backed persistence for kontor_documents_templates. Versions of "the
 * same template" share (organization_id, template_key, language) with an
 * incrementing version_number — same technique as kontor/files'
 * FileRepository.
 */
final class TemplateRepository
{
    public function __construct(
        private readonly \PDO $pdo,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    public function save(DocumentTemplate $template): void
    {
        $organizationId = $this->organizations->internalIdOf($template->organizationId);

        $statement = $this->pdo->prepare(
            'INSERT INTO kontor_documents_templates
                (uid, organization_id, template_key, document_type, language, name, body_html, custom_css,
                 version_number, created_at, created_by, archived_at)
             VALUES
                (:uid, :organization_id, :template_key, :document_type, :language, :name, :body_html, :custom_css,
                 :version_number, :created_at, :created_by, :archived_at)'
        );

        $statement->execute([
            'uid' => $template->uid->toString(),
            'organization_id' => $organizationId,
            'template_key' => $template->templateKey,
            'document_type' => $template->documentType,
            'language' => $template->language,
            'name' => $template->name,
            'body_html' => $template->bodyHtml,
            'custom_css' => $template->customCss,
            'version_number' => $template->versionNumber,
            'created_at' => $template->createdAt->format('Y-m-d H:i:s.u'),
            'created_by' => $template->createdBy,
            'archived_at' => $template->archivedAt?->format('Y-m-d H:i:s.u'),
        ]);
    }

    public function find(string $uid): ?DocumentTemplate
    {
        $statement = $this->pdo->prepare('SELECT * FROM kontor_documents_templates WHERE uid = :uid');
        $statement->execute(['uid' => $uid]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function require(string $uid): DocumentTemplate
    {
        return $this->find($uid) ?? throw new \RuntimeException("Document template \"{$uid}\" was not found.");
    }

    /**
     * @return DocumentTemplate[] all versions, newest first
     */
    public function forOrganization(string $organizationUid): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);
        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_documents_templates
             WHERE organization_id = :organization_id
             ORDER BY created_at DESC'
        );
        $statement->execute(['organization_id' => $organizationId]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * The active (non-archived, newest) version for a template family in a
     * given language. Falls back to English when the requested language
     * has no version — the same "English is always source and fallback"
     * convention as TranslationRegistry (kontor.md#23).
     */
    public function findCurrentVersion(string $organizationUid, string $templateKey, string $language): ?DocumentTemplate
    {
        $found = $this->findCurrentVersionExact($organizationUid, $templateKey, $language);

        if ($found !== null || $language === 'en') {
            return $found;
        }

        return $this->findCurrentVersionExact($organizationUid, $templateKey, 'en');
    }

    /**
     * Exact family lookup for publishing/version management. Unlike
     * findCurrentVersion(), this never falls back to English.
     */
    public function findCurrentVersionExact(string $organizationUid, string $templateKey, string $language): ?DocumentTemplate
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_documents_templates
             WHERE organization_id = :organization_id AND template_key = :template_key AND language = :language
                AND archived_at IS NULL
             ORDER BY version_number DESC
             LIMIT 1'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'template_key' => $templateKey,
            'language' => $language,
        ]);

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    /**
     * @return DocumentTemplate[] every version, newest first
     */
    public function versionHistory(string $organizationUid, string $templateKey, string $language): array
    {
        $organizationId = $this->organizations->internalIdOf($organizationUid);

        $statement = $this->pdo->prepare(
            'SELECT * FROM kontor_documents_templates
             WHERE organization_id = :organization_id AND template_key = :template_key AND language = :language
             ORDER BY version_number DESC'
        );
        $statement->execute([
            'organization_id' => $organizationId,
            'template_key' => $templateKey,
            'language' => $language,
        ]);

        return array_map($this->hydrate(...), $statement->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function archive(string $uid): void
    {
        $statement = $this->pdo->prepare('UPDATE kontor_documents_templates SET archived_at = :now WHERE uid = :uid');
        $statement->execute(['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'), 'uid' => $uid]);
    }

    public function restore(string $uid): void
    {
        $template = $this->require($uid);
        $organizationId = $this->organizations->internalIdOf($template->organizationId);
        $startedTransaction = !$this->pdo->inTransaction();

        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            $archive = $this->pdo->prepare(
                'UPDATE kontor_documents_templates
                 SET archived_at = :now
                 WHERE organization_id = :organization_id
                    AND template_key = :template_key
                    AND language = :language
                    AND uid <> :uid
                    AND archived_at IS NULL'
            );
            $archive->execute([
                'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
                'organization_id' => $organizationId,
                'template_key' => $template->templateKey,
                'language' => $template->language,
                'uid' => $uid,
            ]);

            $restore = $this->pdo->prepare(
                'UPDATE kontor_documents_templates SET archived_at = NULL WHERE uid = :uid'
            );
            $restore->execute(['uid' => $uid]);

            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): DocumentTemplate
    {
        return new DocumentTemplate(
            uid: Uid::fromString($row['uid']),
            organizationId: $this->organizationUidFor((int) $row['organization_id']),
            templateKey: $row['template_key'],
            documentType: $row['document_type'],
            language: $row['language'],
            name: $row['name'],
            bodyHtml: $row['body_html'],
            customCss: $row['custom_css'],
            versionNumber: (int) $row['version_number'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            createdBy: $row['created_by'] !== null ? (int) $row['created_by'] : null,
            archivedAt: $row['archived_at'] !== null ? new \DateTimeImmutable($row['archived_at']) : null,
        );
    }

    private function organizationUidFor(int $organizationId): string
    {
        $statement = $this->pdo->prepare('SELECT uid FROM kontor_organizations WHERE id = :id');
        $statement->execute(['id' => $organizationId]);

        return (string) $statement->fetchColumn();
    }
}
