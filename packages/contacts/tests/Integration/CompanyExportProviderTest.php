<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Infrastructure\Export\CompanyExportProvider;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ExportContext;

final class CompanyExportProviderTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (new CompanyRepository($this->pdo, new OrganizationRepository($this->pdo)))
            ->save(Company::create($this->organizationUid, 'Acme GmbH'));
    }

    public function test_count_and_iterate(): void
    {
        $provider = new CompanyExportProvider($this->pdo, new OrganizationRepository($this->pdo));
        $context = new ExportContext($this->organizationUid, 'user', 'usr_01');

        $this->assertSame(1, $provider->count([], $context));

        $rows = iterator_to_array($provider->iterate([], [], $context));
        $this->assertSame('Acme GmbH', $rows[0]['legal_name']);
    }
}
