<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Application\TagService;
use Kontor\Core\Infrastructure\Persistence\ExtensionRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class TagServiceTest extends DatabaseTestCase
{
    private function service(): TagService
    {
        return new TagService(new ExtensionRepository($this->pdo), new OrganizationRepository($this->pdo));
    }

    public function test_set_then_get_tags(): void
    {
        $service = $this->service();
        $service->setTags($this->organizationUid, 'contact', 'ct_01', ['VIP', 'Lead']);

        $this->assertSame(['vip', 'lead'], $service->tagsFor($this->organizationUid, 'contact', 'ct_01'));
    }

    public function test_tags_are_normalized_lowercase_deduplicated_and_trimmed(): void
    {
        $service = $this->service();
        $service->setTags($this->organizationUid, 'contact', 'ct_01', [' VIP ', 'vip', 'Lead', '']);

        $this->assertSame(['vip', 'lead'], $service->tagsFor($this->organizationUid, 'contact', 'ct_01'));
    }

    public function test_add_tag_appends(): void
    {
        $service = $this->service();
        $service->setTags($this->organizationUid, 'contact', 'ct_01', ['vip']);

        $service->addTag($this->organizationUid, 'contact', 'ct_01', 'lead');

        $this->assertSame(['vip', 'lead'], $service->tagsFor($this->organizationUid, 'contact', 'ct_01'));
    }

    public function test_remove_tag(): void
    {
        $service = $this->service();
        $service->setTags($this->organizationUid, 'contact', 'ct_01', ['vip', 'lead']);

        $service->removeTag($this->organizationUid, 'contact', 'ct_01', 'vip');

        $this->assertSame(['lead'], $service->tagsFor($this->organizationUid, 'contact', 'ct_01'));
    }

    public function test_contacts_and_companies_have_independent_tags(): void
    {
        $service = $this->service();
        $service->setTags($this->organizationUid, 'contact', 'shared_01', ['contact-tag']);
        $service->setTags($this->organizationUid, 'company', 'shared_01', ['company-tag']);

        $this->assertSame(['contact-tag'], $service->tagsFor($this->organizationUid, 'contact', 'shared_01'));
        $this->assertSame(['company-tag'], $service->tagsFor($this->organizationUid, 'company', 'shared_01'));
    }

    public function test_unsupported_entity_type_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->tagsFor($this->organizationUid, 'invoice', 'inv_01');
    }

    public function test_untagged_entity_returns_empty_array(): void
    {
        $this->assertSame([], $this->service()->tagsFor($this->organizationUid, 'contact', 'ct_untagged'));
    }
}
