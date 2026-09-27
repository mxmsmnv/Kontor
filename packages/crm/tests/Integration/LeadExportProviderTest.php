<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Infrastructure\Export\LeadExportProvider;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ExportContext;

final class LeadExportProviderTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new LeadRepository($this->pdo, new OrganizationRepository($this->pdo)))
            ->save(Lead::create($this->organizationUid, 'Big opportunity'));
    }

    public function test_count_and_iterate(): void
    {
        $provider = new LeadExportProvider($this->pdo, new OrganizationRepository($this->pdo));
        $context = new ExportContext($this->organizationUid, 'user', 'usr_01');

        $this->assertSame(1, $provider->count([], $context));
        $rows = iterator_to_array($provider->iterate([], [], $context));
        $this->assertSame('Big opportunity', $rows[0]['title']);
    }

    public function test_unknown_requested_fields_are_ignored_rather_than_causing_a_sql_error(): void
    {
        $provider = new LeadExportProvider($this->pdo, new OrganizationRepository($this->pdo));
        $context = new ExportContext($this->organizationUid, 'user', 'usr_01');

        $rows = iterator_to_array($provider->iterate([], ['uid', 'DROP TABLE kontor_crm_leads; --'], $context));

        $this->assertSame(['uid'], array_keys($rows[0]));
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_crm_leads')->fetchColumn());
    }
}
