<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Unit\Domain;

use Kontor\Contacts\Domain\Contact;
use PHPUnit\Framework\TestCase;

final class ContactTest extends TestCase
{
    public function test_create_composes_display_name_from_names(): void
    {
        $contact = Contact::create('org_01', 'Ada', null, 'Lovelace');

        $this->assertSame('Ada Lovelace', $contact->displayName);
    }

    public function test_create_uses_explicit_display_name_when_given(): void
    {
        $contact = Contact::create('org_01', 'Ada', null, 'Lovelace', displayName: 'The Countess');

        $this->assertSame('The Countess', $contact->displayName);
    }

    public function test_compose_display_name_skips_empty_parts(): void
    {
        $this->assertSame('Ada Lovelace', Contact::composeDisplayName('Ada', null, 'Lovelace'));
        $this->assertSame('Ada Lovelace', Contact::composeDisplayName('Ada', '', 'Lovelace'));
        $this->assertSame('Ada King Lovelace', Contact::composeDisplayName('Ada', 'King', 'Lovelace'));
    }

    public function test_create_defaults(): void
    {
        $contact = Contact::create('org_01', 'Ada', null, 'Lovelace');

        $this->assertSame('individual', $contact->type);
        $this->assertSame('active', $contact->status);
        $this->assertSame('en', $contact->preferredLanguage);
        $this->assertSame([], $contact->metadata);
        $this->assertSame('org_01', $contact->organizationId);
    }

    public function test_each_contact_gets_a_unique_uid(): void
    {
        $a = Contact::create('org_01', 'Ada', null, 'Lovelace');
        $b = Contact::create('org_01', 'Ada', null, 'Lovelace');

        $this->assertFalse($a->uid->equals($b->uid));
    }
}
