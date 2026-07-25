<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\CRM\Infrastructure\Reports\PipelineReportProvider;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\ValueObjects\Money;

final class PipelineReportProviderTest extends DatabaseTestCase
{
    public function test_execute_groups_deal_value_and_count_by_stage(): void
    {
        $fixture = $this->createDefaultPipeline();
        $organizations = new OrganizationRepository($this->pdo);
        $deals = new DealRepository($this->pdo, $organizations);

        $deals->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Deal A', value: Money::ofMinor(1000, 'EUR')));
        $deals->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][0]->uid->toString(), 'Deal B', value: Money::ofMinor(2000, 'EUR')));
        $deals->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $fixture['stages'][1]->uid->toString(), 'Deal C', value: Money::ofMinor(500, 'EUR')));

        $provider = new PipelineReportProvider($this->pdo, $organizations);
        $result = $provider->execute(new ReportQuery($this->organizationUid, filters: ['pipeline_uid' => $fixture['pipeline']->uid->toString()]));

        $this->assertSame(3, $result->totals['deal_count']);
        $this->assertSame(3500, $result->totals['total_value_minor']);

        $byStage = [];
        foreach ($result->rows as $row) {
            $byStage[$row['stage_uid']] = $row;
        }
        $this->assertSame(2, $byStage[$fixture['stages'][0]->uid->toString()]['deal_count']);
        $this->assertSame(3000, $byStage[$fixture['stages'][0]->uid->toString()]['total_value_minor']);
    }

    public function test_key_and_title(): void
    {
        $provider = new PipelineReportProvider($this->pdo, new OrganizationRepository($this->pdo));

        $this->assertSame('crm_pipeline', $provider->key());
        $this->assertSame('CRM Pipeline', $provider->title());
    }
}
