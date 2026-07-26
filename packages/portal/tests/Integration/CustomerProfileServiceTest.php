<?php

declare(strict_types=1);

namespace Kontor\Portal\Tests\Integration;

use InvalidArgumentException;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Migrations\Migration0001CreateContactsTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Portal\Application\CustomerProfileService;

final class CustomerProfileServiceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateContactsTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_contacts', 'kontor_organizations', 'kontor_migrations'];
    }

    private function contacts(): ContactRepository
    {
        return new ContactRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_view_returns_the_contact(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com');
        $contacts->save($contact);

        $viewed = (new CustomerProfileService($contacts))->view($contact->uid->toString());

        $this->assertSame('ada@example.com', $viewed->email);
    }

    public function test_update_changes_an_editable_field(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com', phone: '111');
        $contacts->save($contact);

        $updated = (new CustomerProfileService($contacts))->update($contact->uid->toString(), ['phone' => '222']);

        $this->assertSame('222', $updated->phone);
        $this->assertSame('222', $contacts->require($contact->uid->toString())->phone);
    }

    public function test_update_rejects_a_field_outside_the_editable_allowlist(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com');
        $contacts->save($contact);

        $this->expectException(InvalidArgumentException::class);

        (new CustomerProfileService($contacts))->update($contact->uid->toString(), ['status' => 'archived']);
    }

    public function test_update_rejects_changing_the_assigned_user(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com');
        $contacts->save($contact);

        $this->expectException(InvalidArgumentException::class);

        (new CustomerProfileService($contacts))->update($contact->uid->toString(), ['assignedUserId' => 42]);
    }
}
