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

        $viewed = (new CustomerProfileService($contacts))->view(
            $this->organizationUid,
            $contact->uid->toString(),
        );

        $this->assertSame('ada@example.com', $viewed->email);
    }

    public function test_view_rejects_contact_from_another_organization(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create(
            $this->organizationUid,
            'Cross',
            null,
            'Tenant',
            preferredLanguage: 'en',
        );
        $contacts->save($contact);

        $this->expectException(\InvalidArgumentException::class);
        (new CustomerProfileService($contacts))->view(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            $contact->uid->toString(),
        );
    }

    public function test_update_changes_an_editable_field(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com', phone: '111');
        $contacts->save($contact);

        $updated = (new CustomerProfileService($contacts))->update($this->organizationUid, $contact->uid->toString(), ['phone' => '222']);

        $this->assertSame('222', $updated->phone);
        $this->assertSame('222', $contacts->require($contact->uid->toString())->phone);
    }

    public function test_update_rejects_a_field_outside_the_editable_allowlist(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com');
        $contacts->save($contact);

        $this->expectException(InvalidArgumentException::class);

        (new CustomerProfileService($contacts))->update($this->organizationUid, $contact->uid->toString(), ['status' => 'archived']);
    }

    public function test_update_rejects_changing_the_assigned_user(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com');
        $contacts->save($contact);

        $this->expectException(InvalidArgumentException::class);

        (new CustomerProfileService($contacts))->update($this->organizationUid, $contact->uid->toString(), ['assignedUserId' => 42]);
    }

    public function test_update_rejects_a_contact_outside_the_organization_before_mutation(): void
    {
        $contacts = $this->contacts();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.com', phone: '111');
        $contacts->save($contact);

        try {
            (new CustomerProfileService($contacts))->update('01ARZ3NDEKTSV4RRFFQ69G5FAV', $contact->uid->toString(), ['phone' => '222']);
            $this->fail('Expected the cross-organization update to be rejected.');
        } catch (InvalidArgumentException) {
            $this->assertSame('111', $contacts->require($contact->uid->toString())->phone);
        }
    }
}
