<?php

declare(strict_types=1);

namespace Kontor\Documents\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Documents\Domain\DocumentTemplate;
use Kontor\Documents\Infrastructure\Persistence\TemplateRepository;

final class TemplateRepositoryTest extends DatabaseTestCase
{
    private function repository(): TemplateRepository
    {
        return new TemplateRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $template = DocumentTemplate::create(
            organizationId: $this->organizationUid,
            templateKey: 'quotation',
            documentType: 'quotation',
            language: 'en',
            name: 'Standard quotation',
            bodyHtml: '<p>{{number}}</p>',
            customCss: 'p { color: black; }',
        );

        $repository->save($template);
        $found = $repository->find($template->uid->toString());

        $this->assertNotNull($found);
        $this->assertSame('quotation', $found->templateKey);
        $this->assertSame(1, $found->versionNumber);
        $this->assertSame('p { color: black; }', $found->customCss);
    }

    public function test_find_current_version_returns_the_newest_non_archived_row(): void
    {
        $repository = $this->repository();

        $v1 = DocumentTemplate::create($this->organizationUid, 'quotation', 'quotation', 'en', 'v1', '<p>v1</p>', versionNumber: 1);
        $repository->save($v1);
        $repository->archive($v1->uid->toString());

        $v2 = DocumentTemplate::create($this->organizationUid, 'quotation', 'quotation', 'en', 'v2', '<p>v2</p>', versionNumber: 2);
        $repository->save($v2);

        $current = $repository->findCurrentVersion($this->organizationUid, 'quotation', 'en');

        $this->assertNotNull($current);
        $this->assertSame($v2->uid->toString(), $current->uid->toString());
    }

    public function test_find_current_version_falls_back_to_english_when_language_missing(): void
    {
        $repository = $this->repository();
        $english = DocumentTemplate::create($this->organizationUid, 'quotation', 'quotation', 'en', 'English', '<p>en</p>');
        $repository->save($english);

        $current = $repository->findCurrentVersion($this->organizationUid, 'quotation', 'de');

        $this->assertNotNull($current);
        $this->assertSame($english->uid->toString(), $current->uid->toString());
    }

    public function test_find_current_version_returns_null_when_nothing_published_in_any_language(): void
    {
        $this->assertNull($this->repository()->findCurrentVersion($this->organizationUid, 'invoice', 'fr'));
    }

    public function test_version_history_returns_every_version_newest_first(): void
    {
        $repository = $this->repository();
        $v1 = DocumentTemplate::create($this->organizationUid, 'quotation', 'quotation', 'en', 'v1', '<p>v1</p>', versionNumber: 1);
        $repository->save($v1);
        $repository->archive($v1->uid->toString());
        $v2 = DocumentTemplate::create($this->organizationUid, 'quotation', 'quotation', 'en', 'v2', '<p>v2</p>', versionNumber: 2);
        $repository->save($v2);

        $history = $repository->versionHistory($this->organizationUid, 'quotation', 'en');

        $this->assertCount(2, $history);
        $this->assertSame(2, $history[0]->versionNumber);
        $this->assertSame(1, $history[1]->versionNumber);
    }
}
