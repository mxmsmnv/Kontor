<?php

declare(strict_types=1);

namespace Kontor\Documents\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Documents\Application\TemplateManager;
use Kontor\Documents\Infrastructure\Persistence\TemplateRepository;

final class TemplateManagerTest extends DatabaseTestCase
{
    private function manager(): TemplateManager
    {
        return new TemplateManager(new TemplateRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    public function test_publish_creates_version_one_when_no_template_exists_yet(): void
    {
        $template = $this->manager()->publish(
            $this->organizationUid, 'quotation', 'quotation', 'en', 'Standard quotation', '<p>{{number}}</p>',
        );

        $this->assertSame(1, $template->versionNumber);
        $this->assertFalse($template->isArchived());
    }

    public function test_publishing_again_archives_the_previous_version_and_increments(): void
    {
        $manager = $this->manager();
        $repository = new TemplateRepository($this->pdo, new OrganizationRepository($this->pdo));

        $v1 = $manager->publish($this->organizationUid, 'quotation', 'quotation', 'en', 'v1', '<p>v1</p>');
        $v2 = $manager->publish($this->organizationUid, 'quotation', 'quotation', 'en', 'v2', '<p>v2</p>');

        $this->assertSame(2, $v2->versionNumber);

        $archivedV1 = $repository->require($v1->uid->toString());
        $this->assertTrue($archivedV1->isArchived());

        $current = $repository->findCurrentVersion($this->organizationUid, 'quotation', 'en');
        $this->assertSame($v2->uid->toString(), $current->uid->toString());
    }

    public function test_different_languages_version_independently(): void
    {
        $manager = $this->manager();

        $en = $manager->publish($this->organizationUid, 'quotation', 'quotation', 'en', 'English', '<p>en</p>');
        $fr = $manager->publish($this->organizationUid, 'quotation', 'quotation', 'fr', 'French', '<p>fr</p>');

        $this->assertSame(1, $en->versionNumber);
        $this->assertSame(1, $fr->versionNumber);
    }
}
