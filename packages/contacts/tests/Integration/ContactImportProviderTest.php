<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Application\ContactDuplicateDetector;
use Kontor\Contacts\Infrastructure\Import\ContactImportProvider;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ImportContext;

final class ContactImportProviderTest extends DatabaseTestCase
{
    private function provider(): ContactImportProvider
    {
        $repository = new ContactRepository($this->pdo, new OrganizationRepository($this->pdo));

        return new ContactImportProvider($repository, new ContactDuplicateDetector($repository));
    }

    private function context(bool $dryRun = false): ImportContext
    {
        return new ImportContext($this->organizationUid, 'batch_01', $dryRun, 'user', 'usr_01');
    }

    public function test_validate_requires_a_name(): void
    {
        $result = $this->provider()->validate(['email' => 'ada@example.test'], $this->context());

        $this->assertFalse($result->valid);
        $this->assertSame(['contact.display_name.required'], $result->errors['display_name']);
    }

    public function test_validate_rejects_an_invalid_email(): void
    {
        $result = $this->provider()->validate(['first_name' => 'Ada', 'email' => 'not-an-email'], $this->context());

        $this->assertFalse($result->valid);
        $this->assertSame(['contact.email.invalid'], $result->errors['email']);
    }

    public function test_validate_accepts_a_valid_record(): void
    {
        $result = $this->provider()->validate(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test'], $this->context());

        $this->assertTrue($result->valid);
    }

    public function test_import_creates_a_new_contact(): void
    {
        $provider = $this->provider();
        $record = ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test'];

        $result = $provider->import($record, $this->context());

        $this->assertSame('created', $result->outcome);
        $this->assertNotNull($result->entityUid);
    }

    public function test_import_updates_a_duplicate_found_by_email(): void
    {
        $provider = $this->provider();
        $first = $provider->import(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test'], $this->context());

        $result = $provider->import(
            ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'job_title' => 'Mathematician'],
            $this->context(),
        );

        $this->assertSame('updated', $result->outcome);
        $this->assertSame($first->entityUid, $result->entityUid);
    }

    public function test_find_existing_returns_null_when_no_duplicate(): void
    {
        $uid = $this->provider()->findExisting(['email' => 'nobody@example.test'], $this->context());

        $this->assertNull($uid);
    }
}
