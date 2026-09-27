<?php

namespace ProcessWire;

use Kontor\Contacts\Application\ContactDuplicateDetector;
use Kontor\Contacts\Application\TagService;
use Kontor\Contacts\Health\ContactsHealthCheck;
use Kontor\Contacts\Infrastructure\Backup\ContactsBackupProvider;
use Kontor\Contacts\Infrastructure\Export\CompanyExportProvider;
use Kontor\Contacts\Infrastructure\Export\ContactExportProvider;
use Kontor\Contacts\Infrastructure\Import\CompanyImportProvider;
use Kontor\Contacts\Infrastructure\Import\ContactImportProvider;
use Kontor\Contacts\Infrastructure\API\ContactResource;
use Kontor\Contacts\Infrastructure\Persistence\AddressRepository;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Infrastructure\Persistence\MembershipRepository;
use Kontor\Contacts\Migrations\Migration0001CreateContactsTable;
use Kontor\Contacts\Migrations\Migration0002CreateCompaniesTable;
use Kontor\Contacts\Migrations\Migration0003CreateAddressesTable;
use Kontor\Contacts\Migrations\Migration0004CreateContactCompanyTable;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\ExtensionRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\BackupProviderRegistry;
use Kontor\Core\Infrastructure\Registry\ExportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\ImportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\RepositoryRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Search\Infrastructure\Search\SqlFullTextSearchProvider;

/**
 * KontorContacts bootstrap module (kontor.md Substage 3.1) — the first
 * real business component. Registers import/export providers and a
 * repository (for ImportManager's rollback) into Core's registries, and
 * search providers into KontorSearch's, rather than exposing its own
 * capability: Contacts doesn't provide infrastructure other components
 * consume, it consumes infrastructure Core/Search/Queue already provide.
 */
class KontorContacts extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Contacts',
            'summary' => 'Contacts, companies, addresses, memberships, tags and duplicate detection.',
            'version' => '100',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorContacts',
            'icon' => 'address-book',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorSearch', 'KontorAPI'],
            'permissions' => [
                'kontor-contacts-contact-view' => 'View contacts',
                'kontor-contacts-contact-create' => 'Create contacts',
                'kontor-contacts-contact-edit' => 'Edit contacts',
                'kontor-contacts-contact-archive' => 'Archive contacts',
                'kontor-contacts-contact-delete' => 'Delete contacts',
                'kontor-contacts-company-view' => 'View companies',
                'kontor-contacts-company-create' => 'Create companies',
                'kontor-contacts-company-edit' => 'Edit companies',
                'kontor-contacts-company-archive' => 'Archive companies',
                'kontor-contacts-company-delete' => 'Delete companies',
                'kontor-contacts-merge' => 'Merge duplicate contacts or companies',
                'kontor-contacts-export' => 'Export contacts and companies',
            ],
        ];
    }

    private ?ContactRepository $contactRepository = null;
    private ?CompanyRepository $companyRepository = null;
    private ?AddressRepository $addressRepository = null;
    private ?MembershipRepository $membershipRepository = null;
    private ?TagService $tagService = null;
    private ?ContactDuplicateDetector $duplicateDetector = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        /** @var KontorSearch $searchModule */
        $searchModule = $this->wire()->modules->get('KontorSearch');
        /** @var KontorAPI $apiModule */
        $apiModule = $this->wire()->modules->get('KontorAPI');
        $apiModule->resourceRegistry()->register(new ContactResource($this->contactRepository()));

        $kontor->container()->get(ImportProviderRegistry::class)->register(
            'contact',
            new ContactImportProvider($this->contactRepository(), $this->duplicateDetector())
        );
        $kontor->container()->get(ImportProviderRegistry::class)->register(
            'company',
            new CompanyImportProvider($this->companyRepository())
        );

        $kontor->container()->get(ExportProviderRegistry::class)->register(
            'contact',
            new ContactExportProvider($this->pdo(), $kontor->container()->get(OrganizationRepository::class))
        );
        $kontor->container()->get(ExportProviderRegistry::class)->register(
            'company',
            new CompanyExportProvider($this->pdo(), $kontor->container()->get(OrganizationRepository::class))
        );

        $kontor->container()->get(RepositoryRegistry::class)->register('contact', $this->contactRepository());
        $kontor->container()->get(RepositoryRegistry::class)->register('company', $this->companyRepository());

        $organizations = $kontor->container()->get(OrganizationRepository::class);
        $kontor->container()->get(BackupProviderRegistry::class)->register(
            'contacts',
            new ContactsBackupProvider($this->pdo(), $organizations)
        );

        $searchModule->providerRegistry()->register(new SqlFullTextSearchProvider(
            pdo: $this->pdo(),
            organizations: $organizations,
            providerName: 'contacts',
            entityType: 'contact',
            table: 'kontor_contacts',
            uidColumn: 'uid',
            titleColumn: 'display_name',
            subtitleColumn: 'email',
            fullTextColumns: ['display_name', 'email'],
            additionalConditions: ['deleted_at IS NULL', 'archived_at IS NULL'],
        ));
        $searchModule->providerRegistry()->register(new SqlFullTextSearchProvider(
            pdo: $this->pdo(),
            organizations: $organizations,
            providerName: 'companies',
            entityType: 'company',
            table: 'kontor_companies',
            uidColumn: 'uid',
            titleColumn: 'legal_name',
            subtitleColumn: 'email',
            fullTextColumns: ['legal_name', 'trading_name', 'email'],
            additionalConditions: ['deleted_at IS NULL', 'archived_at IS NULL'],
        ));

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__ . "/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorContacts', $language, $strings);
        }
    }

    public function contactRepository(): ContactRepository
    {
        return $this->contactRepository ??= new ContactRepository($this->pdo(), $this->organizations());
    }

    public function companyRepository(): CompanyRepository
    {
        return $this->companyRepository ??= new CompanyRepository($this->pdo(), $this->organizations());
    }

    public function addressRepository(): AddressRepository
    {
        return $this->addressRepository ??= new AddressRepository($this->pdo(), $this->organizations());
    }

    public function membershipRepository(): MembershipRepository
    {
        return $this->membershipRepository ??= new MembershipRepository($this->pdo(), $this->organizations());
    }

    public function tagService(): TagService
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $this->tagService ??= new TagService(
            $kontor->container()->get(ExtensionRepository::class),
            $this->organizations(),
        );
    }

    public function duplicateDetector(): ContactDuplicateDetector
    {
        return $this->duplicateDetector ??= new ContactDuplicateDetector($this->contactRepository());
    }

    public function healthCheck(): ContactsHealthCheck
    {
        return new ContactsHealthCheck($this->pdo());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateContactsTable(),
            new Migration0002CreateCompaniesTable(),
            new Migration0003CreateAddressesTable(),
            new Migration0004CreateContactCompanyTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('contacts', self::getModuleInfo()['version'], 'contacts');
        $components->enable('contacts');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('contacts', self::getModuleInfo()['version'], 'contacts');
        $components->enable('contacts');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Contacts module removed. Contact and company data was kept intact.'));
    }
}
