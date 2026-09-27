<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Health\ContactsHealthCheck;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class ContactsHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_counts(): void
    {
        (new ContactRepository($this->pdo, new OrganizationRepository($this->pdo)))
            ->save(Contact::create($this->organizationUid, 'Ada', null, 'Lovelace'));

        $result = (new ContactsHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['contacts']);
        $this->assertSame(0, $result->details['companies']);
    }
}
