<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Infrastructure\Export\DealExportProvider;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ExportContext;

final class DealExportProviderTest extends DatabaseTestCase
{
    public function test_count_and_iterate(): void
    {
        $fixture = $this->createDefaultPipeline();
        $organizations = new OrganizationRepository($this->pdo);
        (new DealRepository($this->pdo, $organizations))
            ->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Big deal'));

        $provider = new DealExportProvider($this->pdo, $organizations);
        $context = new ExportContext($this->organizationUid, 'user', 'usr_01');

        $this->assertSame(1, $provider->count([], $context));
        $rows = iterator_to_array($provider->iterate([], [], $context));
        $this->assertSame('Big deal', $rows[0]['title']);
    }
}
