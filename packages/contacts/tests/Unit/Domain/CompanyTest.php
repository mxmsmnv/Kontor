<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Unit\Domain;

use Kontor\Contacts\Domain\Company;
use PHPUnit\Framework\TestCase;

final class CompanyTest extends TestCase
{
    public function test_create_defaults(): void
    {
        $company = Company::create('org_01', 'Acme GmbH');

        $this->assertSame('Acme GmbH', $company->legalName);
        $this->assertSame('active', $company->status);
        $this->assertSame('en', $company->preferredLanguage);
        $this->assertNull($company->tradingName);
        $this->assertSame([], $company->metadata);
    }

    public function test_each_company_gets_a_unique_uid(): void
    {
        $a = Company::create('org_01', 'Acme GmbH');
        $b = Company::create('org_01', 'Acme GmbH');

        $this->assertFalse($a->uid->equals($b->uid));
    }
}
