<?php

namespace ProcessWire;

use Kontor\Contacts\Application\ContactDuplicateDetector;
use Kontor\Contacts\Application\TagService;
use Kontor\Contacts\Domain\Address;
use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Domain\ContactCompanyMembership;
use Kontor\Contacts\Infrastructure\Persistence\AddressRepository;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Contacts\Infrastructure\Persistence\MembershipRepository;
use Kontor\Core\Application\BackupManager;
use Kontor\Core\Application\ExportManager;
use Kontor\Core\Application\ImportManager;
use Kontor\Core\Domain\ImportBatchResult;
use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\ExportContext;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\ValueObjects\Uid;

/**
 * The single Kontor admin application. Business components provide the
 * domain services while this Process module owns navigation and UI.
 */
class ProcessKontor extends Process
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor',
            'summary' => 'Kontor ERP, CRM and business operations admin.',
            'version' => '008',
            'author' => 'Maxim Semenov',
            'icon' => 'cubes',
            'permission' => 'kontor-access',
            'requires' => ['Kontor'],
            'page' => [
                'name' => 'kontor',
                'title' => 'Kontor',
            ],
            'useNavJSON' => false,
            'nav' => [
                ['url' => '', 'label' => 'Dashboard', 'icon' => 'dashboard'],
                ['url' => 'search/', 'label' => 'Search', 'icon' => 'search'],
                [
                    'url' => 'contacts/',
                    'label' => 'Contacts',
                    'icon' => 'address-book',
                    'permission' => 'kontor-contacts-contact-view',
                ],
                [
                    'url' => 'companies/',
                    'label' => 'Companies',
                    'icon' => 'building',
                    'permission' => 'kontor-contacts-company-view',
                ],
                [
                    'url' => 'components/',
                    'label' => 'Components',
                    'icon' => 'cubes',
                    'permission' => 'kontor-components-view',
                ],
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $moduleUrl = $this->wire()->config->urls->get('ProcessKontor');
        $version = (string) (@filemtime(__DIR__ . '/assets/kontor.admin.css') ?: self::getModuleInfo()['version']);
        $this->wire()->config->styles->add($moduleUrl . 'assets/kontor.admin.css?v=' . $version);
    }

    public function ___execute(): string
    {
        $this->setPageTitle($this->_('Kontor · Dashboard'));
        $components = $this->componentRegistry()->all();
        $contactsReady = $this->contactsReady();
        $organizationUid = $contactsReady ? $this->organizationUid() : null;
        $contacts = $contactsReady ? $this->contactRepository()->countActive($organizationUid) : 0;
        $companies = $contactsReady ? $this->companyRepository()->countActive($organizationUid) : 0;
        $recentContacts = $contactsReady ? $this->contactRepository()->findAll($organizationUid, '', 6) : [];

        return $this->renderTemplate('dashboard', [
            'components' => $components,
            'contactsReady' => $contactsReady,
            'contactCount' => $contacts,
            'companyCount' => $companies,
            'recentContacts' => $recentContacts,
        ]);
    }

    public function ___executeContacts(): string
    {
        $this->requirePermission('kontor-contacts-contact-view');
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Contacts'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $showArchived = (string) $this->wire()->input->get('archived') === '1';

        return $this->renderTemplate('contacts', [
            'contacts' => $showArchived
                ? $this->contactRepository()->findArchived($this->organizationUid(), $query)
                : $this->contactRepository()->findAll($this->organizationUid(), $query),
            'query' => $query,
            'showArchived' => $showArchived,
        ]);
    }

    public function ___executeContact(): string
    {
        $this->requireContacts();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $contact = $id !== '' ? $this->contactRepository()->require($id) : null;
        $this->requirePermission($contact === null
            ? 'kontor-contacts-contact-create'
            : 'kontor-contacts-contact-edit');
        $this->setPageTitle($contact === null
            ? $this->_('Kontor · New contact')
            : sprintf($this->_('Kontor · %s'), $contact->displayName));

        $form = $this->buildContactForm($contact);
        $duplicates = [];

        if ($this->wire()->input->post('submit_save')) {
            $form->processInput($this->wire()->input->post);

            if (!$form->getErrors()) {
                if ($contact === null) {
                    $duplicates = $this->duplicateDetector()->findDuplicates(
                        $this->organizationUid(),
                        $this->formValue($form, 'email'),
                        $this->formValue($form, 'phone')
                    );
                }

                if ($duplicates !== [] && !$this->wire()->input->post('confirm_duplicate')) {
                    $this->addDuplicateConfirmation($form);
                } else {
                    $contact = $this->saveContactFromForm($form, $contact);
                    $this->message($this->_('Contact saved.'));
                    $this->wire()->session->redirect('../contact/?id=' . rawurlencode($contact->uid->toString()));
                }
            }
        }

        $relationships = $contact === null ? [] : $this->contactRelationships($contact);

        return $this->renderTemplate('entity-form', [
            'form' => $form,
            'backUrl' => '../contacts/',
            'backLabel' => $this->_('Back to contacts'),
            'eyebrow' => $this->_('Contacts'),
            'title' => $contact?->displayName ?: $this->_('Create contact'),
            'description' => $contact === null
                ? $this->_('Add a person to your shared customer directory.')
                : $this->_('Keep identity, communication and assignment details in one place.'),
            'entity' => $contact,
            'entityType' => 'contact',
            'relationships' => $relationships,
            'availableCompanies' => $contact === null
                ? []
                : $this->companyRepository()->findAll($this->organizationUid(), '', 250),
            'addresses' => $contact === null
                ? []
                : $this->addressRepository()->forOwner('contact', $contact->uid->toString()),
            'duplicates' => $duplicates,
            'tags' => $contact === null
                ? []
                : $this->tagService()->tagsFor(
                    $this->organizationUid(),
                    'contact',
                    $contact->uid->toString()
                ),
        ]);
    }

    public function ___executeCompanies(): string
    {
        $this->requirePermission('kontor-contacts-company-view');
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Companies'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $showArchived = (string) $this->wire()->input->get('archived') === '1';

        return $this->renderTemplate('companies', [
            'companies' => $showArchived
                ? $this->companyRepository()->findArchived($this->organizationUid(), $query)
                : $this->companyRepository()->findAll($this->organizationUid(), $query),
            'query' => $query,
            'showArchived' => $showArchived,
        ]);
    }

    public function ___executeCompany(): string
    {
        $this->requireContacts();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $company = $id !== '' ? $this->companyRepository()->require($id) : null;
        $this->requirePermission($company === null
            ? 'kontor-contacts-company-create'
            : 'kontor-contacts-company-edit');
        $this->setPageTitle($company === null
            ? $this->_('Kontor · New company')
            : sprintf($this->_('Kontor · %s'), $company->legalName));

        $form = $this->buildCompanyForm($company);

        if ($this->wire()->input->post('submit_save')) {
            $form->processInput($this->wire()->input->post);

            if (!$form->getErrors()) {
                $company = $this->saveCompanyFromForm($form, $company);
                $this->message($this->_('Company saved.'));
                $this->wire()->session->redirect('../company/?id=' . rawurlencode($company->uid->toString()));
            }
        }

        $relationships = $company === null ? [] : $this->companyRelationships($company);

        return $this->renderTemplate('entity-form', [
            'form' => $form,
            'backUrl' => '../companies/',
            'backLabel' => $this->_('Back to companies'),
            'eyebrow' => $this->_('Companies'),
            'title' => $company?->legalName ?: $this->_('Create company'),
            'description' => $company === null
                ? $this->_('Add an organization, customer or partner.')
                : $this->_('Manage commercial identity and contact information.'),
            'entity' => $company,
            'entityType' => 'company',
            'relationships' => $relationships,
            'availableCompanies' => [],
            'addresses' => $company === null
                ? []
                : $this->addressRepository()->forOwner('company', $company->uid->toString()),
            'duplicates' => [],
            'tags' => $company === null
                ? []
                : $this->tagService()->tagsFor(
                    $this->organizationUid(),
                    'company',
                    $company->uid->toString()
                ),
        ]);
    }

    public function ___executeSearch(): string
    {
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Search'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));
        $result = null;

        if (mb_strlen($query) >= 2) {
            $result = $this->searchService()->search(new SearchQuery(
                organizationId: $this->organizationUid(),
                term: $query,
                entityTypes: ['contact', 'company'],
                limit: 30,
            ));
        }

        return $this->renderTemplate('search', [
            'query' => $query,
            'result' => $result,
        ]);
    }

    public function ___executeAddress(): void
    {
        $this->requirePost();
        $ownerType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('owner_type'),
            ['contact', 'company']
        );
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['add', 'delete']
        );
        $this->requireAction($ownerType, ['contact', 'company']);
        $this->requireAction($action, ['add', 'delete']);
        $ownerUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('owner_uid'));
        $owner = $ownerType === 'contact'
            ? $this->contactRepository()->require($ownerUid)
            : $this->companyRepository()->require($ownerUid);
        $this->requireSameOrganization($owner->organizationId);
        $this->requirePermission($ownerType === 'contact'
            ? 'kontor-contacts-contact-edit'
            : 'kontor-contacts-company-edit');

        if ($action === 'delete') {
            $addressUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('address_uid'));
            $address = $this->addressRepository()->find($addressUid);

            if (
                $address === null
                || $address->ownerType !== $ownerType
                || !hash_equals($address->ownerUid, $ownerUid)
                || !hash_equals($address->organizationId, $this->organizationUid())
            ) {
                throw new WirePermissionException($this->_('Address does not belong to this record.'));
            }

            $this->addressRepository()->delete($addressUid);
            $this->message($this->_('Address removed.'));
        } else {
            $line1 = $this->wire()->sanitizer->text(trim((string) $this->wire()->input->post('line1')));
            $city = $this->wire()->sanitizer->text(trim((string) $this->wire()->input->post('city')));
            $countryCode = strtoupper(trim((string) $this->wire()->input->post('country_code')));

            if ($line1 === '' || $city === '' || preg_match('/^[A-Z]{2}$/', $countryCode) !== 1) {
                $this->error($this->_('Street, city and a two-letter country code are required.'));
                $this->wire()->session->redirect('../' . $ownerType . '/?id=' . rawurlencode($ownerUid));
            }

            $addresses = $this->addressRepository()->forOwner($ownerType, $ownerUid);
            $addressType = $this->wire()->sanitizer->option(
                (string) $this->wire()->input->post('address_type'),
                ['billing', 'shipping', 'home', 'work', 'other']
            ) ?? 'billing';
            $this->addressRepository()->save(Address::create(
                organizationId: $this->organizationUid(),
                ownerType: $ownerType,
                ownerUid: $ownerUid,
                line1: $line1,
                city: $city,
                countryCode: $countryCode,
                addressType: $addressType,
                line2: $this->nullablePostText('line2'),
                region: $this->nullablePostText('region'),
                postalCode: $this->nullablePostText('postal_code'),
                isPrimary: $addresses === [] || (bool) $this->wire()->input->post('is_primary'),
            ));
            $this->message($this->_('Address added.'));
        }

        $this->wire()->session->redirect('../' . $ownerType . '/?id=' . rawurlencode($ownerUid));
    }

    public function ___executeTags(): void
    {
        $this->requirePost();
        $ownerType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('owner_type'),
            ['contact', 'company']
        );
        $this->requireAction($ownerType, ['contact', 'company']);
        $ownerUid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('owner_uid'));
        $owner = $ownerType === 'contact'
            ? $this->contactRepository()->require($ownerUid)
            : $this->companyRepository()->require($ownerUid);
        $this->requireSameOrganization($owner->organizationId);
        $this->requirePermission($ownerType === 'contact'
            ? 'kontor-contacts-contact-edit'
            : 'kontor-contacts-company-edit');
        $rawTags = explode(',', (string) $this->wire()->input->post('tags'));
        $tags = [];

        foreach (array_slice($rawTags, 0, 20) as $rawTag) {
            $tag = mb_substr($this->wire()->sanitizer->text(trim($rawTag)), 0, 50);

            if ($tag !== '') {
                $tags[] = $tag;
            }
        }

        $this->tagService()->setTags($this->organizationUid(), $ownerType, $ownerUid, $tags);
        $this->message($this->_('Tags updated.'));
        $this->wire()->session->redirect('../' . $ownerType . '/?id=' . rawurlencode($ownerUid));
    }

    public function ___executeExport(): void
    {
        $this->requireContacts();
        $this->requirePermission('kontor-contacts-export');
        $entityType = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('entity'),
            ['contact', 'company']
        );
        $format = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('format'),
            ['csv', 'json', 'jsonl', 'xlsx']
        );
        $this->requireAction($entityType, ['contact', 'company']);
        $this->requireAction($format, ['csv', 'json', 'jsonl', 'xlsx']);

        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_export_');

        if ($temporary === false) {
            throw new WireException($this->_('Could not create an export file.'));
        }

        $path = $temporary . '.' . $format;
        rename($temporary, $path);
        register_shutdown_function(static function () use ($path): void {
            if (is_file($path)) {
                unlink($path);
            }
        });

        $total = $this->exportManager()->run(
            entityType: $entityType,
            filters: [],
            fields: [],
            context: new ExportContext(
                organizationId: $this->organizationUid(),
                actorType: 'user',
                actorId: (string) $this->wire()->user->id,
            ),
            writer: (new FormatResolver())->writer($format),
            path: $path,
        );
        $date = (new \DateTimeImmutable())->format('Y-m-d');
        $filename = 'kontor-' . ($entityType === 'contact' ? 'contacts' : 'companies') . "-{$date}.{$format}";
        $this->wire()->log->save('kontor', "Exported {$total} {$entityType} records as {$format}.");
        wireSendFile($path, [
            'forceDownload' => true,
            'downloadFilename' => $filename,
            'exit' => true,
        ]);
    }

    public function ___executeImport(): string
    {
        $this->requireContacts();
        $this->requirePermission('kontor-import');
        $this->setPageTitle($this->_('Kontor · Import preview'));
        $entityType = $this->wire()->sanitizer->option(
            (string) ($this->wire()->input->post('entity') ?: $this->wire()->input->get('entity')),
            ['contact', 'company']
        ) ?? 'contact';
        $result = null;
        $filename = null;
        $previewToken = null;
        $backupId = null;
        $this->cleanupImportPreviews();

        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            $this->requirePost();

            try {
                if ($this->wire()->input->post('commit_import')) {
                    [$result, $filename, $backupId] = $this->commitImportPreview(
                        $this->wire()->sanitizer->text((string) $this->wire()->input->post('preview_token'))
                    );
                    $entityType = $result->entityType;
                    $this->message(sprintf(
                        $this->_('Import completed: %d created, %d updated. Verified backup %s was created first.'),
                        $result->created,
                        $result->updated,
                        $backupId
                    ));
                } else {
                    [$path, $format, $filename] = $this->receiveImportFile();
                    $batchId = Uid::generate()->toString();

                    try {
                        $result = $this->importManager()->run(
                            entityType: $entityType,
                            reader: (new FormatResolver())->reader($format),
                            path: $path,
                            context: new ImportContext(
                                organizationId: $this->organizationUid(),
                                batchId: $batchId,
                                dryRun: true,
                                actorType: 'user',
                                actorId: (string) $this->wire()->user->id,
                            ),
                        );

                        if ($result->totalRows > 0 && $result->failed === 0) {
                            $previewToken = $this->storeImportPreview(
                                $batchId,
                                $path,
                                $format,
                                $filename,
                                $entityType,
                                $result->updated
                            );
                            $path = '';
                        }
                    } finally {
                        if ($path !== '' && is_file($path)) {
                            unlink($path);
                        }
                    }
                }
            } catch (\Throwable $exception) {
                $this->error($this->_('Import failed: ') . $exception->getMessage());
            }
        }

        return $this->renderTemplate('import', [
            'entityType' => $entityType,
            'result' => $result,
            'filename' => $filename,
            'previewToken' => $previewToken,
            'backupId' => $backupId,
        ]);
    }

    public function ___executeContactStatus(): void
    {
        $this->requirePost();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore']
        );
        $this->requireAction($action, ['archive', 'restore']);
        $contact = $this->contactRepository()->require($id);
        $this->requireSameOrganization($contact->organizationId);
        $this->requirePermission('kontor-contacts-contact-archive');

        $action === 'restore'
            ? $this->contactRepository()->restore($id)
            : $this->contactRepository()->archive($id);
        $this->message($action === 'restore' ? $this->_('Contact restored.') : $this->_('Contact archived.'));
        $this->wire()->session->redirect('../contacts/' . ($action === 'restore' ? '?archived=1' : ''));
    }

    public function ___executeCompanyStatus(): void
    {
        $this->requirePost();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['archive', 'restore']
        );
        $this->requireAction($action, ['archive', 'restore']);
        $company = $this->companyRepository()->require($id);
        $this->requireSameOrganization($company->organizationId);
        $this->requirePermission('kontor-contacts-company-archive');

        $action === 'restore'
            ? $this->companyRepository()->restore($id)
            : $this->companyRepository()->archive($id);
        $this->message($action === 'restore' ? $this->_('Company restored.') : $this->_('Company archived.'));
        $this->wire()->session->redirect('../companies/' . ($action === 'restore' ? '?archived=1' : ''));
    }

    public function ___executeRelationship(): void
    {
        $this->requirePost();
        $contactId = $this->wire()->sanitizer->text((string) $this->wire()->input->post('contact_id'));
        $companyId = $this->wire()->sanitizer->text((string) $this->wire()->input->post('company_id'));
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['add', 'end']
        );
        $this->requireAction($action, ['add', 'end']);
        $contact = $this->contactRepository()->require($contactId);
        $company = $this->companyRepository()->require($companyId);
        $this->requireSameOrganization($contact->organizationId);
        $this->requireSameOrganization($company->organizationId);
        $this->requirePermission('kontor-contacts-contact-edit');

        if ($action === 'end') {
            $this->membershipRepository()->end($contactId, $companyId, new \DateTimeImmutable('today'));
            $this->message($this->_('Company relationship ended.'));
        } else {
            $role = $this->wire()->sanitizer->text((string) $this->wire()->input->post('role'));
            $department = $this->wire()->sanitizer->text((string) $this->wire()->input->post('department'));
            $this->membershipRepository()->save(new ContactCompanyMembership(
                organizationId: $this->organizationUid(),
                contactUid: $contactId,
                companyUid: $companyId,
                role: $role !== '' ? $role : $this->_('Member'),
                department: $department !== '' ? $department : null,
                isPrimary: false,
                startedAt: new \DateTimeImmutable('today'),
                endedAt: null,
            ));
            $this->message($this->_('Company relationship added.'));
        }

        $this->wire()->session->redirect('../contact/?id=' . rawurlencode($contactId));
    }

    public function ___executeComponents(): string
    {
        $this->requirePermission('kontor-components-view');
        $this->setPageTitle($this->_('Kontor · Components'));

        return $this->renderTemplate('components', [
            'components' => $this->componentRegistry()->all(),
        ]);
    }

    private function buildContactForm(?Contact $contact): InputfieldForm
    {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './' . ($contact ? '?id=' . rawurlencode($contact->uid->toString()) : '');
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form');

        $this->addTextField($form, 'display_name', $this->_('Display name'), $contact?->displayName, true, 100);
        $this->addTextField($form, 'first_name', $this->_('First name'), $contact?->firstName, false, 50);
        $this->addTextField($form, 'last_name', $this->_('Last name'), $contact?->lastName, false, 50);
        $this->addEmailField($form, 'email', $this->_('Email'), $contact?->email, 50);
        $this->addTextField($form, 'phone', $this->_('Phone'), $contact?->phone, false, 50);
        $this->addTextField($form, 'mobile', $this->_('Mobile'), $contact?->mobile, false, 50);
        $this->addTextField($form, 'job_title', $this->_('Job title'), $contact?->jobTitle, false, 50);
        $this->addStatusField($form, $contact?->status ?? 'active');
        $this->addTextareaField($form, 'notes', $this->_('Notes'), $contact?->notes);
        $this->addSubmit($form, $this->_('Save contact'));

        return $form;
    }

    private function buildCompanyForm(?Company $company): InputfieldForm
    {
        /** @var InputfieldForm $form */
        $form = $this->wire()->modules->get('InputfieldForm');
        $form->action = './' . ($company ? '?id=' . rawurlencode($company->uid->toString()) : '');
        $form->addClass('InputfieldFormFocusFirst kontor-entity-form');

        $this->addTextField($form, 'legal_name', $this->_('Legal name'), $company?->legalName, true, 100);
        $this->addTextField($form, 'trading_name', $this->_('Trading name'), $company?->tradingName, false, 50);
        $this->addTextField($form, 'registration_number', $this->_('Registration number'), $company?->registrationNumber, false, 50);
        $this->addEmailField($form, 'email', $this->_('Email'), $company?->email, 50);
        $this->addTextField($form, 'phone', $this->_('Phone'), $company?->phone, false, 50);
        $this->addTextField($form, 'website', $this->_('Website'), $company?->website, false, 50);
        $this->addTextField($form, 'vat_number', $this->_('VAT number'), $company?->vatNumber, false, 50);
        $this->addStatusField($form, $company?->status ?? 'active');
        $this->addTextareaField($form, 'notes', $this->_('Notes'), $company?->notes);
        $this->addSubmit($form, $this->_('Save company'));

        return $form;
    }

    private function saveContactFromForm(InputfieldForm $form, ?Contact $contact): Contact
    {
        if ($contact === null) {
            $contact = Contact::create(
                organizationId: $this->organizationUid(),
                firstName: $this->formValue($form, 'first_name'),
                middleName: null,
                lastName: $this->formValue($form, 'last_name'),
                displayName: $this->requiredFormValue($form, 'display_name'),
            );
        }

        $contact->displayName = $this->requiredFormValue($form, 'display_name');
        $contact->firstName = $this->formValue($form, 'first_name');
        $contact->lastName = $this->formValue($form, 'last_name');
        $contact->email = $this->formValue($form, 'email');
        $contact->phone = $this->formValue($form, 'phone');
        $contact->mobile = $this->formValue($form, 'mobile');
        $contact->jobTitle = $this->formValue($form, 'job_title');
        $contact->status = $this->requiredFormValue($form, 'status');
        $contact->notes = $this->formValue($form, 'notes');
        $this->contactRepository()->save($contact);

        return $contact;
    }

    private function saveCompanyFromForm(InputfieldForm $form, ?Company $company): Company
    {
        if ($company === null) {
            $company = Company::create(
                organizationId: $this->organizationUid(),
                legalName: $this->requiredFormValue($form, 'legal_name'),
            );
        }

        $company->legalName = $this->requiredFormValue($form, 'legal_name');
        $company->tradingName = $this->formValue($form, 'trading_name');
        $company->registrationNumber = $this->formValue($form, 'registration_number');
        $company->email = $this->formValue($form, 'email');
        $company->phone = $this->formValue($form, 'phone');
        $company->website = $this->formValue($form, 'website');
        $company->vatNumber = $this->formValue($form, 'vat_number');
        $company->status = $this->requiredFormValue($form, 'status');
        $company->notes = $this->formValue($form, 'notes');
        $this->companyRepository()->save($company);

        return $company;
    }

    private function addTextField(
        InputfieldForm $form,
        string $name,
        string $label,
        ?string $value,
        bool $required,
        int $width
    ): void {
        /** @var InputfieldText $field */
        $field = $this->wire()->modules->get('InputfieldText');
        $field->name = $name;
        $field->label = $label;
        $field->value = $value ?? '';
        $field->required = $required;
        $field->columnWidth = $width;
        $form->add($field);
    }

    private function addEmailField(
        InputfieldForm $form,
        string $name,
        string $label,
        ?string $value,
        int $width
    ): void {
        /** @var InputfieldEmail $field */
        $field = $this->wire()->modules->get('InputfieldEmail');
        $field->name = $name;
        $field->label = $label;
        $field->value = $value ?? '';
        $field->columnWidth = $width;
        $form->add($field);
    }

    private function addStatusField(InputfieldForm $form, string $value): void
    {
        /** @var InputfieldSelect $field */
        $field = $this->wire()->modules->get('InputfieldSelect');
        $field->name = 'status';
        $field->label = $this->_('Status');
        $field->addOptions([
            'active' => $this->_('Active'),
            'inactive' => $this->_('Inactive'),
        ]);
        $field->value = $value;
        $field->columnWidth = 50;
        $form->add($field);
    }

    private function addTextareaField(
        InputfieldForm $form,
        string $name,
        string $label,
        ?string $value
    ): void {
        /** @var InputfieldTextarea $field */
        $field = $this->wire()->modules->get('InputfieldTextarea');
        $field->name = $name;
        $field->label = $label;
        $field->value = $value ?? '';
        $field->rows = 5;
        $form->add($field);
    }

    private function addSubmit(InputfieldForm $form, string $label): void
    {
        /** @var InputfieldSubmit $submit */
        $submit = $this->wire()->modules->get('InputfieldSubmit');
        $submit->name = 'submit_save';
        $submit->value = $label;
        $submit->icon = 'save';
        $form->add($submit);
    }

    private function addDuplicateConfirmation(InputfieldForm $form): void
    {
        /** @var InputfieldCheckbox $field */
        $field = $this->wire()->modules->get('InputfieldCheckbox');
        $field->name = 'confirm_duplicate';
        $field->label = $this->_('Create this contact anyway');
        $field->description = $this->_('I reviewed the possible duplicate contacts shown above.');
        $field->required = true;
        $form->insertBefore($field, $form->getChildByName('submit_save'));
    }

    private function formValue(InputfieldForm $form, string $name): ?string
    {
        $value = trim((string) $form->getChildByName($name)?->value);

        return $value === '' ? null : $value;
    }

    private function requiredFormValue(InputfieldForm $form, string $name): string
    {
        return trim((string) $form->getChildByName($name)?->value);
    }

    private function nullablePostText(string $name): ?string
    {
        $value = trim((string) $this->wire()->input->post($name));

        return $value === '' ? null : $this->wire()->sanitizer->text($value);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function receiveImportFile(): array
    {
        $upload = $_FILES['import_file'] ?? null;

        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new WireException($this->_('Choose a file to preview.'));
        }

        if ((int) ($upload['size'] ?? 0) > 10 * 1024 * 1024) {
            throw new WireException($this->_('Import files are limited to 10 MB.'));
        }

        $originalName = basename((string) ($upload['name'] ?? 'import'));
        $format = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $format = $format === 'ndjson' ? 'jsonl' : $format;
        $this->requireAction($format, ['csv', 'json', 'jsonl', 'xlsx']);
        $temporary = tempnam($this->wire()->config->paths->cache, 'kontor_import_');

        if ($temporary === false) {
            throw new WireException($this->_('Could not create a temporary import file.'));
        }

        $path = $temporary . '.' . $format;
        rename($temporary, $path);

        if (!move_uploaded_file((string) $upload['tmp_name'], $path)) {
            @unlink($path);
            throw new WireException($this->_('Could not store the uploaded file.'));
        }

        return [$path, $format, $originalName];
    }

    private function storeImportPreview(
        string $batchId,
        string $path,
        string $format,
        string $filename,
        string $entityType,
        int $updated
    ): string {
        $previews = $this->wire()->session->get('kontorImportPreviews');
        $previews = is_array($previews) ? $previews : [];
        $previews[$batchId] = [
            'path' => $path,
            'format' => $format,
            'filename' => $filename,
            'entityType' => $entityType,
            'updated' => $updated,
            'checksum' => hash_file('sha256', $path),
            'expires' => time() + 3600,
        ];
        $this->wire()->session->set('kontorImportPreviews', $previews);

        return $batchId;
    }

    /**
     * @return array{0: ImportBatchResult, 1: string, 2: string}
     */
    private function commitImportPreview(string $token): array
    {
        $previews = $this->wire()->session->get('kontorImportPreviews');
        $preview = is_array($previews) ? ($previews[$token] ?? null) : null;

        if (!is_array($preview) || (int) ($preview['expires'] ?? 0) < time()) {
            throw new WireException($this->_('This import preview has expired. Upload the file again.'));
        }

        $path = (string) ($preview['path'] ?? '');

        if (
            !$this->isManagedImportPath($path)
            || !is_file($path)
            || !hash_equals((string) ($preview['checksum'] ?? ''), (string) hash_file('sha256', $path))
        ) {
            $this->removeImportPreview($token);
            throw new WireException($this->_('The preview file is no longer available or has changed.'));
        }

        $entityType = (string) $preview['entityType'];
        $format = (string) $preview['format'];

        if ((int) $preview['updated'] > 0) {
            $this->requirePermission('kontor-import-update');
        }

        $backup = $this->backupManager()->create(
            component: 'contacts',
            kind: 'snapshot',
            organizationId: $this->organizationUid(),
            reason: "Before import {$token}",
        );

        if (!$backup->verified) {
            throw new WireException($this->_('The pre-import Contacts backup could not be verified.'));
        }

        try {
            $result = $this->importManager()->run(
                entityType: $entityType,
                reader: (new FormatResolver())->reader($format),
                path: $path,
                context: new ImportContext(
                    organizationId: $this->organizationUid(),
                    batchId: $token,
                    dryRun: false,
                    actorType: 'user',
                    actorId: (string) $this->wire()->user->id,
                ),
                backupVerification: new BackupVerification(
                    verified: true,
                    checksum: $backup->checksum,
                ),
                auditOrganizationId: $this->organizationInternalId(),
            );

            if ($result->failed > 0) {
                throw new WireException($this->_('The live import reported failed rows.'));
            }
        } catch (\Throwable $exception) {
            $restore = $this->backupManager()->restore(
                $backup->path,
                'contacts',
                $this->organizationUid()
            );

            if (!$restore->success) {
                throw new WireException(
                    $this->_('Import failed and automatic restore also failed: ') . implode('; ', $restore->errors),
                    previous: $exception
                );
            }

            $this->markImportAuditRestored($token);
            throw new WireException(
                $this->_('Import failed; Contacts data was restored from the verified backup.'),
                previous: $exception
            );
        } finally {
            $this->removeImportPreview($token);
        }

        return [$result, (string) $preview['filename'], $backup->id];
    }

    private function cleanupImportPreviews(): void
    {
        $previews = $this->wire()->session->get('kontorImportPreviews');

        if (!is_array($previews)) {
            return;
        }

        foreach ($previews as $token => $preview) {
            if (!is_array($preview) || (int) ($preview['expires'] ?? 0) < time()) {
                $this->removeImportPreview((string) $token);
            }
        }
    }

    private function removeImportPreview(string $token): void
    {
        $previews = $this->wire()->session->get('kontorImportPreviews');
        $previews = is_array($previews) ? $previews : [];
        $preview = $previews[$token] ?? null;

        if (is_array($preview) && $this->isManagedImportPath((string) ($preview['path'] ?? ''))) {
            @unlink((string) $preview['path']);
        }

        unset($previews[$token]);
        $this->wire()->session->set('kontorImportPreviews', $previews);
    }

    private function isManagedImportPath(string $path): bool
    {
        return $path !== ''
            && dirname($path) === rtrim($this->wire()->config->paths->cache, '/\\')
            && str_starts_with(basename($path), 'kontor_import_');
    }

    private function markImportAuditRestored(string $batchId): void
    {
        $statement = $this->wire()->database->pdo()->prepare(
            "UPDATE kontor_audit_events
             SET action = 'import.restored'
             WHERE correlation_id = :batch_id
               AND action IN ('import.created', 'import.updated')"
        );
        $statement->execute(['batch_id' => $batchId]);
    }

    private function renderTemplate(string $name, array $variables): string
    {
        $variables['adminUrl'] = $this->wire()->config->urls->admin . 'kontor/';
        $variables['csrfName'] = $this->wire()->session->CSRF->getTokenName();
        $variables['csrfValue'] = $this->wire()->session->CSRF->getTokenValue();
        $variables['e'] = static fn (mixed $value): string => htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
        extract($variables, EXTR_SKIP);
        ob_start();
        include __DIR__ . '/templates/admin/' . $name . '.php';

        return (string) ob_get_clean();
    }

    private function setPageTitle(string $title): void
    {
        $this->headline($title);
        $this->browserTitle($title);
    }

    private function requirePermission(string $permission): void
    {
        $user = $this->wire()->user;

        if (!$user->isSuperuser() && !$user->hasPermission($permission)) {
            throw new WirePermissionException(
                sprintf($this->_('You do not have the required permission: %s'), $permission)
            );
        }
    }

    private function requirePost(): void
    {
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            throw new WireException($this->_('This action requires a POST request.'));
        }

        $this->wire()->session->CSRF->validate();
    }

    private function requireSameOrganization(string $organizationUid): void
    {
        if (!hash_equals($this->organizationUid(), $organizationUid)) {
            throw new WirePermissionException($this->_('This record belongs to another organization.'));
        }
    }

    /**
     * @param string[] $allowed
     */
    private function requireAction(?string $action, array $allowed): void
    {
        if ($action === null || !in_array($action, $allowed, true)) {
            throw new WireException($this->_('Invalid action.'));
        }
    }

    private function contactsReady(): bool
    {
        return $this->wire()->modules->isInstalled('KontorContacts');
    }

    private function requireContacts(): void
    {
        if (!$this->contactsReady()) {
            throw new WireException($this->_('The Kontor Contacts component is not installed.'));
        }
    }

    private function contactRepository(): ContactRepository
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->contactRepository();
    }

    private function companyRepository(): CompanyRepository
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->companyRepository();
    }

    private function membershipRepository(): MembershipRepository
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->membershipRepository();
    }

    private function addressRepository(): AddressRepository
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->addressRepository();
    }

    private function duplicateDetector(): ContactDuplicateDetector
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->duplicateDetector();
    }

    private function tagService(): TagService
    {
        /** @var KontorContacts $module */
        $module = $this->wire()->modules->get('KontorContacts');

        return $module->tagService();
    }

    /**
     * @return array<int, array{membership: ContactCompanyMembership, entity: Company}>
     */
    private function contactRelationships(Contact $contact): array
    {
        $relationships = [];

        foreach ($this->membershipRepository()->forContact($contact->uid->toString()) as $membership) {
            try {
                $company = $this->companyRepository()->require($membership->companyUid);
            } catch (\RuntimeException) {
                continue;
            }

            if ($company->organizationId === $contact->organizationId) {
                $relationships[] = ['membership' => $membership, 'entity' => $company];
            }
        }

        return $relationships;
    }

    /**
     * @return array<int, array{membership: ContactCompanyMembership, entity: Contact}>
     */
    private function companyRelationships(Company $company): array
    {
        $relationships = [];

        foreach ($this->membershipRepository()->forCompany($company->uid->toString()) as $membership) {
            try {
                $contact = $this->contactRepository()->require($membership->contactUid);
            } catch (\RuntimeException) {
                continue;
            }

            if ($contact->organizationId === $company->organizationId) {
                $relationships[] = ['membership' => $membership, 'entity' => $contact];
            }
        }

        return $relationships;
    }

    private function organizationUid(): string
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        $organization = $kontor->container()
            ->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');

        return $organization->uid->toString();
    }

    private function organizationInternalId(): int
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()
            ->get(OrganizationRepository::class)
            ->internalIdOf($this->organizationUid());
    }

    private function componentRegistry(): ComponentRegistry
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(ComponentRegistry::class);
    }

    private function exportManager(): ExportManager
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(ExportManager::class);
    }

    private function importManager(): ImportManager
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(ImportManager::class);
    }

    private function backupManager(): BackupManager
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(BackupManager::class);
    }

    private function searchService(): GlobalSearchService
    {
        /** @var KontorSearch $module */
        $module = $this->wire()->modules->get('KontorSearch');

        return $module->globalSearchService();
    }
}
