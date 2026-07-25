<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Infrastructure\Import\DealImportProvider;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ImportContext;

final class DealImportProviderTest extends DatabaseTestCase
{
    private function provider(): DealImportProvider
    {
        return new DealImportProvider(new DealRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    private function context(): ImportContext
    {
        return new ImportContext($this->organizationUid, 'batch_01', false, 'user', 'usr_01');
    }

    public function test_validate_requires_title_pipeline_and_stage(): void
    {
        $result = $this->provider()->validate([], $this->context());

        $this->assertFalse($result->valid);
        $this->assertArrayHasKey('title', $result->errors);
        $this->assertArrayHasKey('pipeline_uid', $result->errors);
        $this->assertArrayHasKey('stage_uid', $result->errors);
    }

    public function test_import_creates_a_deal(): void
    {
        $fixture = $this->createDefaultPipeline();

        $result = $this->provider()->import(
            ['title' => 'Big deal', 'pipeline_uid' => $fixture['pipeline']->uid->toString(), 'stage_uid' => $fixture['stages'][0]->uid->toString()],
            $this->context(),
        );

        $this->assertSame('created', $result->outcome);
    }
}
