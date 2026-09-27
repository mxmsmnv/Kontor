<?php

declare(strict_types=1);

namespace Kontor\Documents\Domain;

use Kontor\SDK\ValueObjects\Uid;

/**
 * A single version of a document template. `create()` starts a new family
 * at version 1; `newVersion()` (TemplateManager) is what actually
 * increments version_number when editing an existing template — this class
 * itself doesn't know about the family's other versions, same split as
 * kontor_files (row = one version, TemplateRepository = the version index).
 */
final class DocumentTemplate
{
    public function __construct(
        public readonly Uid $uid,
        public readonly string $organizationId,
        public readonly string $templateKey,
        public readonly string $documentType,
        public readonly string $language,
        public string $name,
        public string $bodyHtml,
        public ?string $customCss,
        public readonly int $versionNumber,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?int $createdBy,
        public ?\DateTimeImmutable $archivedAt = null,
    ) {
    }

    public static function create(
        string $organizationId,
        string $templateKey,
        string $documentType,
        string $language,
        string $name,
        string $bodyHtml,
        ?string $customCss = null,
        int $versionNumber = 1,
        ?int $createdBy = null,
    ): self {
        return new self(
            uid: Uid::generate(),
            organizationId: $organizationId,
            templateKey: $templateKey,
            documentType: $documentType,
            language: $language,
            name: $name,
            bodyHtml: $bodyHtml,
            customCss: $customCss,
            versionNumber: $versionNumber,
            createdAt: new \DateTimeImmutable(),
            createdBy: $createdBy,
        );
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }
}
