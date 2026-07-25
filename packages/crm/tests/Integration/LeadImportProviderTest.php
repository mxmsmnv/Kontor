<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Infrastructure\Import\LeadImportProvider;
use Kontor\CRM\Infrastructure\Persistence\LeadRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ImportContext;

final class LeadImportProviderTest extends DatabaseTestCase
{
    private function provider(): LeadImportProvider
    {
        return new LeadImportProvider(new LeadRepository($this->pdo, new OrganizationRepository($this->pdo)));
    }

    private function context(): ImportContext
    {
        return new ImportContext($this->organizationUid, 'batch_01', false, 'user', 'usr_01');
    }

    public function test_validate_requires_a_title(): void
    {
        $result = $this->provider()->validate([], $this->context());

        $this->assertFalse($result->valid);
        $this->assertSame(['lead.title.required'], $result->errors['title']);
    }

    public function test_validate_requires_currency_alongside_value(): void
    {
        $result = $this->provider()->validate(['title' => 'Lead', 'estimated_value_minor' => 1000], $this->context());

        $this->assertFalse($result->valid);
    }

    public function test_import_creates_a_lead(): void
    {
        $result = $this->provider()->import(['title' => 'Big opportunity', 'contact_uid' => 'ct_01'], $this->context());

        $this->assertSame('created', $result->outcome);
    }
}
