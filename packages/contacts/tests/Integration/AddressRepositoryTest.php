<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Domain\Address;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\AddressRepository;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class AddressRepositoryTest extends DatabaseTestCase
{
    private function repository(): AddressRepository
    {
        return new AddressRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace');
        (new ContactRepository($this->pdo, new OrganizationRepository($this->pdo)))->save($contact);

        $address = Address::create(
            $this->organizationUid, 'contact', $contact->uid->toString(),
            '1 Infinite Loop', 'Cupertino', 'us', isPrimary: true,
        );

        $repository = $this->repository();
        $repository->save($address);
        $found = $repository->find($address->uid->toString());

        $this->assertSame('1 Infinite Loop', $found->line1);
        $this->assertSame('US', $found->countryCode);
        $this->assertTrue($found->isPrimary);
    }

    public function test_for_owner_returns_primary_first(): void
    {
        $repository = $this->repository();
        $ownerUid = 'ct_01';

        $repository->save(Address::create($this->organizationUid, 'contact', $ownerUid, 'Line A', 'City A', 'US'));
        $primary = Address::create($this->organizationUid, 'contact', $ownerUid, 'Line B', 'City B', 'US', isPrimary: true);
        $repository->save($primary);

        $addresses = $repository->forOwner('contact', $ownerUid);

        $this->assertCount(2, $addresses);
        $this->assertSame($primary->uid->toString(), $addresses[0]->uid->toString());
    }

    public function test_saving_a_new_primary_unsets_the_previous_primary(): void
    {
        $repository = $this->repository();
        $ownerUid = 'ct_01';
        $first = Address::create(
            $this->organizationUid,
            'contact',
            $ownerUid,
            'Line A',
            'City A',
            'US',
            isPrimary: true
        );
        $second = Address::create(
            $this->organizationUid,
            'contact',
            $ownerUid,
            'Line B',
            'City B',
            'US',
            isPrimary: true
        );

        $repository->save($first);
        $repository->save($second);

        $this->assertFalse($repository->find($first->uid->toString())->isPrimary);
        $this->assertTrue($repository->find($second->uid->toString())->isPrimary);
    }

    public function test_delete_removes_the_address(): void
    {
        $repository = $this->repository();
        $address = Address::create($this->organizationUid, 'contact', 'ct_01', 'Line A', 'City A', 'US');
        $repository->save($address);

        $repository->delete($address->uid->toString());

        $this->assertNull($repository->find($address->uid->toString()));
    }
}
