<?php

namespace ProcessWire;

use Kontor\Contacts\Domain\Company;
use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Persistence\CompanyRepository;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;

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
            'version' => '003',
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

        return $this->renderTemplate('contacts', [
            'contacts' => $this->contactRepository()->findAll($this->organizationUid(), $query),
            'query' => $query,
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

        if ($this->wire()->input->post('submit_save')) {
            $form->processInput($this->wire()->input->post);

            if (!$form->getErrors()) {
                $contact = $this->saveContactFromForm($form, $contact);
                $this->message($this->_('Contact saved.'));
                $this->wire()->session->redirect('../contact/?id=' . rawurlencode($contact->uid->toString()));
            }
        }

        return $this->renderTemplate('entity-form', [
            'form' => $form,
            'backUrl' => '../contacts/',
            'backLabel' => $this->_('Back to contacts'),
            'eyebrow' => $this->_('Contacts'),
            'title' => $contact?->displayName ?: $this->_('Create contact'),
            'description' => $contact === null
                ? $this->_('Add a person to your shared customer directory.')
                : $this->_('Keep identity, communication and assignment details in one place.'),
        ]);
    }

    public function ___executeCompanies(): string
    {
        $this->requirePermission('kontor-contacts-company-view');
        $this->requireContacts();
        $this->setPageTitle($this->_('Kontor · Companies'));
        $query = $this->wire()->sanitizer->text((string) $this->wire()->input->get('q'));

        return $this->renderTemplate('companies', [
            'companies' => $this->companyRepository()->findAll($this->organizationUid(), $query),
            'query' => $query,
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

        return $this->renderTemplate('entity-form', [
            'form' => $form,
            'backUrl' => '../companies/',
            'backLabel' => $this->_('Back to companies'),
            'eyebrow' => $this->_('Companies'),
            'title' => $company?->legalName ?: $this->_('Create company'),
            'description' => $company === null
                ? $this->_('Add an organization, customer or partner.')
                : $this->_('Manage commercial identity and contact information.'),
        ]);
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

    private function formValue(InputfieldForm $form, string $name): ?string
    {
        $value = trim((string) $form->getChildByName($name)?->value);

        return $value === '' ? null : $value;
    }

    private function requiredFormValue(InputfieldForm $form, string $name): string
    {
        return trim((string) $form->getChildByName($name)?->value);
    }

    private function renderTemplate(string $name, array $variables): string
    {
        $variables['adminUrl'] = $this->wire()->config->urls->admin . 'kontor/';
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

    private function organizationUid(): string
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');
        $organization = $kontor->container()
            ->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');

        return $organization->uid->toString();
    }

    private function componentRegistry(): ComponentRegistry
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(ComponentRegistry::class);
    }
}
