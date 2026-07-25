<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Infrastructure\Import\CompanyImportProvider;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ImportContext;

final class CompanyImportProviderTest extends DatabaseTestCase
{
    private function provider(): CompanyImportProvider
    {
        return new CompanyImportProvider(new CompanyRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    private function context(): ImportContext
    {
        return new ImportContext($this->organizationUid, 'batch_01', false, 'user', 'usr_01');
    }

    public function test_validate_requires_legal_name(): void
    {
        $result = $this->provider()->validate(['email' => 'billing@acme.test'], $this->context());

        $this->assertFalse($result->valid);
        $this->assertSame(['company.legal_name.required'], $result->errors['legal_name']);
    }

    public function test_import_creates_then_updates_by_vat_number(): void
    {
        $provider = $this->provider();

        $first = $provider->import(['legal_name' => 'Acme GmbH', 'vat_number' => 'DE123456789'], $this->context());
        $this->assertSame('created', $first->outcome);

        $second = $provider->import(
            ['legal_name' => 'Acme GmbH', 'vat_number' => 'DE123456789', 'website' => 'https://acme.test'],
            $this->context(),
        );

        $this->assertSame('updated', $second->outcome);
        $this->assertSame($first->entityUid, $second->entityUid);
    }
}
