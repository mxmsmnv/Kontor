<?php

declare(strict_types=1);

namespace Kontor\Contacts\Application;

use Kontor\Core\Infrastructure\Persistence\ExtensionRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use InvalidArgumentException;

/**
 * Substage 3.1 "tags" — a thin wrapper over Core's generic
 * ExtensionRepository (kontor.md#11.8) rather than a dedicated table:
 * tags are a small, freeform, per-entity label set, exactly what
 * extension_key/value_json already models. Scoped to 'contact' and
 * 'company' entity types, stored under this component's own name so
 * other components' tags on the same entity never collide
 * (owner_component is part of the table's unique key).
 */
final class TagService
{
    private const OWNER_COMPONENT = 'KontorContacts';
    private const EXTENSION_KEY = 'tags';
    private const SUPPORTED_ENTITY_TYPES = ['contact', 'company'];

    public function __construct(
        private readonly ExtensionRepository $extensions,
        private readonly OrganizationRepository $organizations,
    ) {
    }

    /**
     * @return string[]
     */
    public function tagsFor(string $organizationUid, string $entityType, string $entityUid): array
    {
        $this->assertSupported($entityType);

        $value = $this->extensions->get(
            $this->organizations->internalIdOf($organizationUid),
            self::OWNER_COMPONENT,
            $entityType,
            $entityUid,
            self::EXTENSION_KEY,
        );

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * @param string[] $tags
     */
    public function setTags(string $organizationUid, string $entityType, string $entityUid, array $tags): void
    {
        $this->assertSupported($entityType);

        $normalized = array_values(array_unique(array_map(
            static fn (string $tag): string => mb_strtolower(trim($tag)),
            array_filter($tags, static fn (string $tag): bool => trim($tag) !== '')
        )));

        $this->extensions->set(
            $this->organizations->internalIdOf($organizationUid),
            self::OWNER_COMPONENT,
            $entityType,
            $entityUid,
            self::EXTENSION_KEY,
            $normalized,
        );
    }

    public function addTag(string $organizationUid, string $entityType, string $entityUid, string $tag): void
    {
        $tags = $this->tagsFor($organizationUid, $entityType, $entityUid);
        $tags[] = $tag;

        $this->setTags($organizationUid, $entityType, $entityUid, $tags);
    }

    public function removeTag(string $organizationUid, string $entityType, string $entityUid, string $tag): void
    {
        $normalizedTag = mb_strtolower(trim($tag));
        $tags = array_filter(
            $this->tagsFor($organizationUid, $entityType, $entityUid),
            static fn (string $existing): bool => $existing !== $normalizedTag
        );

        $this->setTags($organizationUid, $entityType, $entityUid, $tags);
    }

    private function assertSupported(string $entityType): void
    {
        if (!in_array($entityType, self::SUPPORTED_ENTITY_TYPES, true)) {
            throw new InvalidArgumentException(
                "TagService only supports entity types \"".implode('", "', self::SUPPORTED_ENTITY_TYPES)."\", got \"{$entityType}\"."
            );
        }
    }
}
