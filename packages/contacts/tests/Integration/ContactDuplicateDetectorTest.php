<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Application\ContactDuplicateDetector;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class ContactDuplicateDetectorTest extends DatabaseTestCase
{
    private function repository(): ContactRepository
    {
        return new ContactRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_finds_a_duplicate_by_email(): void
    {
        $repository = $this->repository();
        $existing = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.test');
        $repository->save($existing);

        $detector = new ContactDuplicateDetector($repository);
        $duplicates = $detector->findDuplicates($this->organizationUid, 'ada@example.test', null);

        $this->assertCount(1, $duplicates);
        $this->assertSame($existing->uid->toString(), $duplicates[0]->uid->toString());
    }

    public function test_finds_a_duplicate_by_phone(): void
    {
        $repository = $this->repository();
        $existing = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', phone: '+1-555-0100');
        $repository->save($existing);

        $detector = new ContactDuplicateDetector($repository);

        $this->assertTrue($detector->hasDuplicate($this->organizationUid, null, '+1-555-0100'));
    }

    public function test_no_match_returns_empty(): void
    {
        $detector = new ContactDuplicateDetector($this->repository());

        $this->assertFalse($detector->hasDuplicate($this->organizationUid, 'nobody@example.test', null));
    }

    public function test_matching_by_both_email_and_phone_does_not_duplicate_the_same_contact(): void
    {
        $repository = $this->repository();
        $existing = Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', email: 'ada@example.test', phone: '+1-555-0100');
        $repository->save($existing);

        $detector = new ContactDuplicateDetector($repository);
        $duplicates = $detector->findDuplicates($this->organizationUid, 'ada@example.test', '+1-555-0100');

        $this->assertCount(1, $duplicates);
    }
}
