<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Unit\Domain;

use Kontor\CRM\Domain\Lead;
use PHPUnit\Framework\TestCase;

final class LeadTest extends TestCase
{
    public function test_create_defaults(): void
    {
        $lead = Lead::create('org_01', 'Big opportunity');

        $this->assertSame('new', $lead->status);
        $this->assertSame('medium', $lead->priority);
        $this->assertFalse($lead->isConverted());
    }

    public function test_is_qualified_for_conversion_requires_a_contact_or_company(): void
    {
        $unqualified = Lead::create('org_01', 'Big opportunity');
        $this->assertFalse($unqualified->isQualifiedForConversion());

        $withContact = Lead::create('org_01', 'Big opportunity', contactUid: 'ct_01');
        $this->assertTrue($withContact->isQualifiedForConversion());

        $withCompany = Lead::create('org_01', 'Big opportunity', companyUid: 'cmp_01');
        $this->assertTrue($withCompany->isQualifiedForConversion());
    }

    public function test_is_converted(): void
    {
        $lead = Lead::create('org_01', 'Big opportunity');
        $lead->status = 'converted';

        $this->assertTrue($lead->isConverted());
    }
}
