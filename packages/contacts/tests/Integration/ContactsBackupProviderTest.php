<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Application\TagService;
use Kontor\Contacts\Domain\Address;
use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Domain\ContactCompanyMembership;
use Kontor\Contacts\Infrastructure\Backup\ContactsBackupProvider;
use Kontor\Contacts\Infrastructure\Persistence\AddressRepository;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Infrastructure\Persistence\MembershipRepository;
use Kontor\Core\Application\BackupManager;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\ExtensionRepository;
use Kontor\Core\Infrastructure\Registry\BackupProviderRegistry;

final class ContactsBackupProviderTest extends DatabaseTestCase
{
    private string $backupRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->backupRoot = sys_get_temp_dir() . '/kontor-contacts-backup-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->backupRoot ?? '');
        parent::tearDown();
    }

    public function test_verified_backup_restores_all_contact_owned_tables(): void
    {
        $organizations = new OrganizationRepository($this->pdo);
        $contacts = new ContactRepository($this->pdo, $organizations);
        $companies = new CompanyRepository($this->pdo, $organizations);
        $addresses = new AddressRepository($this->pdo, $organizations);
        $memberships = new MembershipRepository($this->pdo, $organizations);
        $extensions = new ExtensionRepository($this->pdo);
        $tags = new TagService($extensions, $organizations);
        $contact = Contact::create(
            $this->organizationUid,
            'Ada',
            null,
            'Lovelace',
            email: 'ada@example.test'
        );
        $company = Company::create($this->organizationUid, 'Analytical Engines Ltd');
        $contacts->save($contact);
        $companies->save($company);
        $addresses->save(Address::create(
            $this->organizationUid,
            'contact',
            $contact->uid->toString(),
            '1 Computing Way',
            'London',
            'GB',
            isPrimary: true
        ));
        $memberships->save(new ContactCompanyMembership(
            $this->organizationUid,
            $contact->uid->toString(),
            $company->uid->toString(),
            'Founder',
            null,
            true,
            null,
            null
        ));
        $tags->setTags($this->organizationUid, 'contact', $contact->uid->toString(), ['customer', 'vip']);
        $extensions->set(
            $organizations->internalIdOf($this->organizationUid),
            'AnotherComponent',
            'contact',
            $contact->uid->toString(),
            'unrelated',
            ['kept' => true]
        );

        $registry = new BackupProviderRegistry();
        $registry->register('contacts', new ContactsBackupProvider($this->pdo, $organizations));
        $manager = new BackupManager($registry, $this->backupRoot);
        $backup = $manager->create('contacts', 'snapshot', $this->organizationUid, 'integration test');

        $this->assertTrue($backup->verified);
        $this->assertSame(5, $backup->itemCount);

        $this->pdo->exec("DELETE FROM kontor_extensions WHERE owner_component = 'KontorContacts'");
        $this->pdo->exec('DELETE FROM kontor_contact_company');
        $this->pdo->exec('DELETE FROM kontor_addresses');
        $this->pdo->exec('DELETE FROM kontor_companies');
        $this->pdo->exec('DELETE FROM kontor_contacts');

        $result = $manager->restore($backup->path, 'contacts', $this->organizationUid);

        $this->assertTrue($result->success);
        $this->assertSame(5, $result->restoredCount);
        $this->assertSame('Ada Lovelace', $contacts->require($contact->uid->toString())->displayName);
        $this->assertSame('Analytical Engines Ltd', $companies->require($company->uid->toString())->legalName);
        $this->assertCount(1, $addresses->forOwner('contact', $contact->uid->toString()));
        $this->assertCount(1, $memberships->forContact($contact->uid->toString()));
        $this->assertSame(
            ['customer', 'vip'],
            $tags->tagsFor($this->organizationUid, 'contact', $contact->uid->toString())
        );
        $this->assertSame(
            ['kept' => true],
            $extensions->get(
                $organizations->internalIdOf($this->organizationUid),
                'AnotherComponent',
                'contact',
                $contact->uid->toString(),
                'unrelated'
            )
        );
    }

    private function removeDirectory(string $directory): void
    {
        if ($directory === '' || !is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
