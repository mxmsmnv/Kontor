<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Unit\Domain;

use Kontor\Contacts\Domain\Address;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function test_create_defaults(): void
    {
        $address = Address::create('org_01', 'contact', 'ct_01', '1 Infinite Loop', 'Cupertino', 'us');

        $this->assertSame('billing', $address->addressType);
        $this->assertFalse($address->isPrimary);
        $this->assertSame([], $address->metadata);
    }

    public function test_country_code_is_uppercased(): void
    {
        $address = Address::create('org_01', 'contact', 'ct_01', '1 Infinite Loop', 'Cupertino', 'us');

        $this->assertSame('US', $address->countryCode);
    }
}
