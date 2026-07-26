<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class ContactRepositoryTest extends DatabaseTestCase
{
    private function repository(): ContactRepository
    {
        return new ContactRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.test');

        $repository->save($contact);
        $found = $repository->find($contact->uid->toString());

        $this->assertSame('Ada Lovelace', $found->displayName);
        $this->assertSame('ada@example.test', $found->email);
        $this->assertSame($this->organizationUid, $found->organizationId);
    }

    public function test_require_throws_for_unknown_uid(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository()->require(\Kontor\SDK\ValueObjects\Uid::generate()->toString());
    }

    public function test_save_upserts_rather_than_duplicating(): void
    {
        $repository = $this->repository();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace');

        $repository->save($contact);
        $contact->jobTitle = 'Mathematician';
        $repository->save($contact);

        $found = $repository->find($contact->uid->toString());
        $this->assertSame('Mathematician', $found->jobTitle);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_contacts')->fetchColumn();
        $this->assertSame(1, $count);
    }

    public function test_archive_then_restore(): void
    {
        $repository = $this->repository();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace');
        $repository->save($contact);

        $repository->archive($contact->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_contacts')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['archived_at']);

        $repository->restore($contact->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_contacts')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['archived_at']);
    }

    public function test_delete_is_distinct_from_archive_and_hides_from_find(): void
    {
        $repository = $this->repository();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace');
        $repository->save($contact);

        $repository->delete($contact->uid->toString());

        $this->assertNull($repository->find($contact->uid->toString()));
    }

    public function test_find_by_email_and_phone(): void
    {
        $repository = $this->repository();
        $contact = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.test', phone: '+1-555-0100');
        $repository->save($contact);

        $this->assertSame($contact->uid->toString(), $repository->findByEmail($this->organizationUid, 'ada@example.test')->uid->toString());
        $this->assertSame($contact->uid->toString(), $repository->findByPhone($this->organizationUid, '+1-555-0100')->uid->toString());
        $this->assertNull($repository->findByEmail($this->organizationUid, 'nobody@example.test'));
    }

    public function test_find_all_searches_active_contacts_and_count_excludes_archived(): void
    {
        $repository = $this->repository();
        $ada = Contact::create(
            $this->organizationUid,
            'Ada',
            null,
            'Lovelace',
            email: 'ada@example.test',
            jobTitle: 'Mathematician'
        );
        $grace = Contact::create(
            $this->organizationUid,
            'Grace',
            null,
            'Hopper',
            email: 'grace@example.test'
        );
        $repository->save($ada);
        $repository->save($grace);

        $matches = $repository->findAll($this->organizationUid, 'Mathematician');
        $this->assertCount(1, $matches);
        $this->assertSame('Ada Lovelace', $matches[0]->displayName);
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'Mathematician'));
        $firstPage = $repository->findAll($this->organizationUid, limit: 1);
        $secondPage = $repository->findAll($this->organizationUid, limit: 1, offset: 1);
        $this->assertNotSame($firstPage[0]->uid->toString(), $secondPage[0]->uid->toString());
        $this->assertSame(2, $repository->countActive($this->organizationUid));

        $repository->archive($grace->uid->toString());
        $this->assertSame(1, $repository->countActive($this->organizationUid));
        $this->assertSame(
            ['Grace Hopper'],
            array_map(static fn (Contact $contact): string => $contact->displayName, $repository->findArchived($this->organizationUid))
        );
        $this->assertSame(1, $repository->countMatching($this->organizationUid, archived: true));
    }

    public function test_save_rejects_a_non_contact_entity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository()->save(new \stdClass());
    }
}
