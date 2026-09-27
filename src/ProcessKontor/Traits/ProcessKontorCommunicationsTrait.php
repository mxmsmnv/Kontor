<?php

namespace ProcessWire;

use Kontor\Collaboration\Domain\Comment;
use Kontor\Collaboration\Domain\Note;
use Kontor\Catalog\Application\PriceListDuplicator;
use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Domain\Category;
use Kontor\Catalog\Domain\PriceList;
use Kontor\Catalog\Domain\PriceListEntry;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Catalog\Infrastructure\Persistence\CategoryRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceListRepository;
use Kontor\Catalog\Infrastructure\Persistence\PriceRepository;
use Kontor\Catalog\Support\TaxCode;
use Kontor\Catalog\Support\UnitOfMeasure;
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
use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Domain\Lead;
use Kontor\CRM\Domain\Pipeline;
use Kontor\CRM\Domain\Stage;
use Kontor\Core\Application\AuditChangePresenter;
use Kontor\Core\Application\AuditCsvExporter;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Application\BackupManager;
use Kontor\Core\Application\BackupOverviewBuilder;
use Kontor\Core\Application\ComponentOverviewBuilder;
use Kontor\Core\Application\EntityActionResolver;
use Kontor\Core\Application\ExportManager;
use Kontor\Core\Application\HealthCheckRunner;
use Kontor\Core\Application\HealthOverviewBuilder;
use Kontor\Core\Application\ImportManager;
use Kontor\Core\Application\NavigationAvailability;
use Kontor\Core\Domain\ImportBatchResult;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Health\CoreHealthCheck;
use Kontor\Core\Infrastructure\Backup\BackupArchiveBuilder;
use Kontor\Core\Infrastructure\ImportExport\FormatResolver;
use Kontor\Core\Infrastructure\Persistence\AuditEventRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Expenses\Domain\Expense;
use Kontor\Expenses\Domain\ExpenseCategory;
use Kontor\Expenses\Application\LedgerExpensePostingService;
use Kontor\Inventory\Domain\Warehouse;
use Kontor\Invoices\Application\LedgerInvoicePostingService;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Germany\DTO\LocalizedInvoiceInput;
use Kontor\Germany\DTO\LocalizedLineItemInput;
use Kontor\Germany\DTO\LocalizedPartyInput;
use Kontor\Ledger\Domain\Account;
use Kontor\Ledger\DTO\LedgerLineInput;
use Kontor\Payments\Application\LedgerAllocationPostingService;
use Kontor\Payments\Domain\Payment;
use Kontor\Projects\Domain\BillableItem;
use Kontor\Projects\Domain\Project;
use Kontor\Projects\Domain\ProjectMilestone;
use Kontor\Purchasing\Domain\PurchaseOrder;
use Kontor\Purchasing\Domain\Supplier;
use Kontor\Queue\Infrastructure\Persistence\JobRepository;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\Search\Application\GlobalSearchService;
use Kontor\SDK\DTO\BackupVerification;
use Kontor\SDK\DTO\AIRequest;
use Kontor\SDK\DTO\ExportContext;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ReportQuery;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\Events\KontorEvent;
use Kontor\SDK\ValueObjects\Money;
use Kontor\SDK\ValueObjects\Uid;
use Kontor\Tasks\Domain\Task;
use Kontor\Workflow\Domain\ApprovalRequest;

/**
 * Administrative feature slice composed by ProcessKontor.
 *
 * @internal
 */
trait ProcessKontorCommunicationsTrait
{

    public function ___executeMail(): string
    {
        $this->requireMail();
        $this->requirePermission('kontor-mail-message-view');
        $module = $this->mailModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->messageRepository()->require($id) : null;
        if ($selected !== null) {
            $this->requireSameOrganization($selected->organizationId);
        }
        $relations = $selected !== null
            ? $module->entityLinking()->linkedEntities(
                $this->organizationUid(),
                $selected->uid->toString(),
            )
            : [];
        $this->setPageTitle($selected === null
            ? $this->_('Kontor · Mail')
            : sprintf($this->_('Kontor · %s'), $selected->subject));

        return $this->renderTemplate('mail', [
            'mailboxes' => $module->mailboxRepository()->forOrganization($this->organizationUid()),
            'messages' => $module->messageRepository()->forOrganization($this->organizationUid()),
            'selected' => $selected,
            'relations' => $relations,
            'relationViews' => $this->mailRelationViews($relations),
            'entityTargets' => $this->mailEntityTargets(),
            'assignedLabel' => $selected?->assignedTo !== null
                ? $this->mailAssignedUserLabel($selected->assignedTo)
                : null,
            'adapters' => $module->inboundAdapterRegistry()->all(),
            'canManageMailboxes' => $this->can('kontor-mail-mailbox-manage'),
            'canSend' => $this->can('kontor-mail-message-send'),
        ]);
    }

    public function ___executeMailMailbox(): void
    {
        $this->requirePost();
        $this->requireMail();
        $this->requirePermission('kontor-mail-mailbox-manage');
        $name = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('name')
        ));
        $email = strtolower(trim((string) $this->wire()->input->post('email_address')));
        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new WireException($this->_('Mailbox name and valid email address are required.'));
        }
        $mailbox = $this->mailModule()->mailboxes()->open(
            $this->organizationUid(),
            mb_substr($name, 0, 255),
            mb_substr($email, 0, 320),
            (int) $this->wire()->user->id,
        );
        $this->audit('mail', 'mailbox', $mailbox->uid->toString(), 'created');
        $this->message($this->_('Shared mailbox created.'));
        $this->wire()->session->redirect('../mail/');
    }

    public function ___executeMailOutbound(): void
    {
        $this->requirePost();
        $this->requireMail();
        $this->requirePermission('kontor-mail-message-send');
        $mailbox = $this->mailboxFromPost();
        $from = strtolower(trim((string) $this->wire()->input->post('from_address')));
        $to = $this->mailAddressesFromPost('to_addresses');
        $cc = $this->mailAddressesFromPost('cc_addresses', required: false);
        $subject = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('subject')
        ));
        $body = trim((string) $this->wire()->input->post('body_text'));
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false || $subject === '' || $body === '') {
            throw new WireException($this->_('Valid sender, subject, and body are required.'));
        }
        $deliveryMode = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('delivery_mode'),
            ['simulate', 'live']
        );
        $dryRun = $deliveryMode !== null && $deliveryMode !== ''
            ? $deliveryMode === 'simulate'
            : (bool) $this->wire()->input->post('dry_run');
        $service = $dryRun
            ? $this->mailModule()->outboundWithSender(
                new class implements \Kontor\Mail\Contracts\MailSenderInterface {
                    public function send(
                        string $fromAddress,
                        array $toAddresses,
                        array $ccAddresses,
                        string $subject,
                        string $bodyText,
                    ): void {
                    }
                }
            )
            : $this->mailModule()->outbound();
        $message = $service->send(
            $this->organizationUid(),
            $mailbox?->uid->toString(),
            $from,
            $to,
            $cc,
            mb_substr($subject, 0, 255),
            $body,
            (int) $this->wire()->user->id,
        );
        $this->audit('mail', 'message', $message->uid->toString(), 'sent', metadata: [
            'dryRun' => $dryRun,
            'status' => $message->status,
        ]);
        $this->message($dryRun
            ? $this->_('Outbound message simulated and recorded.')
            : $this->_('Outbound message processed.'));
        $this->wire()->session->redirect(
            '../mail/?id=' . rawurlencode($message->uid->toString())
        );
    }

    public function ___executeMailInbound(): void
    {
        $this->requirePost();
        $this->requireMail();
        $this->requirePermission('kontor-mail-mailbox-manage');
        $mailbox = $this->mailboxFromPost();
        $adapterKey = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('adapter')
        );
        $raw = trim((string) $this->wire()->input->post('raw_email'));
        $adapter = $this->mailModule()->inboundAdapterRegistry()->get($adapterKey);
        if (!$adapter instanceof \Kontor\Mail\Infrastructure\Adapters\RawEmailForwardAdapter) {
            throw new WireException($this->_('The selected adapter does not accept raw email input.'));
        }
        if ($raw === '' || strlen($raw) > 1048576) {
            throw new WireException($this->_('Raw email is required and must be at most 1 MB.'));
        }
        $adapter->pushRaw($raw);
        $messages = $this->mailModule()->inbound()->poll(
            $adapter,
            $this->organizationUid(),
            $mailbox?->uid->toString(),
        );
        if ($messages === []) {
            throw new WireException($this->_('No valid inbound message was parsed.'));
        }
        $message = $messages[0];
        $this->audit('mail', 'message', $message->uid->toString(), 'received');
        $this->message($this->_('Inbound message received.'));
        $this->wire()->session->redirect(
            '../mail/?id=' . rawurlencode($message->uid->toString())
        );
    }

    public function ___executeMailLink(): void
    {
        $this->requirePost();
        $this->requireMail();
        $this->requirePermission('kontor-mail-message-view');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('message_uid')
        );
        $message = $this->mailModule()->messageRepository()->require($uid);
        $this->requireSameOrganization($message->organizationId);
        $target = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('entity_target')
        );
        if ($target !== '' && str_contains($target, ':')) {
            [$entityType, $entityUid] = explode(':', $target, 2);
        } else {
            $entityType = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('entity_type')
            );
            $entityUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('entity_uid')
            );
        }
        if ($entityType === '' || $entityUid === '') {
            throw new WireException($this->_('Choose a record to connect.'));
        }
        $availableTargets = array_column($this->mailEntityTargets(), 'value');
        if (!in_array($entityType . ':' . $entityUid, $availableTargets, true)) {
            throw new Wire404Exception($this->_('The selected record is not available.'));
        }
        $relationUid = $this->mailModule()->entityLinking()->link(
            $this->organizationUid(),
            $uid,
            $entityType,
            $entityUid,
            (int) $this->wire()->user->id,
        );
        $this->audit('mail', 'relation', $relationUid, 'created');
        $this->message($this->_('Message linked to entity.'));
        $this->wire()->session->redirect('../mail/?id=' . rawurlencode($uid));
    }

    public function ___executePortal(): string
    {
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $module = $this->portalModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->accountRepository()->require($id) : null;
        if ($selected !== null) {
            $this->requireSameOrganization($selected->organizationId);
        }

        $contact = null;
        $quotationRows = [];
        $invoiceRows = [];
        if ($selected !== null) {
            $contact = $module->profile()->view(
                $selected->organizationId,
                $selected->contactUid,
            );
            foreach ($module->quotationRepository()->forContact(
                $this->organizationUid(),
                $selected->contactUid,
            ) as $quotation) {
                $files = $module->files()->filesForDocument(
                    'quotation',
                    $quotation->uid->toString(),
                );
                $quotationRows[] = [
                    'quotation' => $quotation,
                    'files' => array_map(
                        fn (array $file): array => $file + [
                            'downloadUrl' => $module->files()->downloadUrl(
                                (string) $file['uid'],
                                new \DateTimeImmutable('+15 minutes'),
                            ),
                        ],
                        $files,
                    ),
                ];
            }
            foreach ($module->invoiceRepository()->forContact(
                $this->organizationUid(),
                $selected->contactUid,
            ) as $invoice) {
                $files = $module->files()->filesForDocument(
                    'invoice',
                    $invoice->uid->toString(),
                );
                $invoiceRows[] = [
                    'invoice' => $invoice,
                    'payments' => $module->payments()->paymentsForInvoice(
                        $invoice->uid->toString()
                    ),
                    'files' => array_map(
                        fn (array $file): array => $file + [
                            'downloadUrl' => $module->files()->downloadUrl(
                                (string) $file['uid'],
                                new \DateTimeImmutable('+15 minutes'),
                            ),
                        ],
                        $files,
                    ),
                ];
            }
        }

        $verification = $this->wire()->session->get('kontorPortalVerification');
        $this->wire()->session->set('kontorPortalVerification', null);
        $this->setPageTitle($this->_('Kontor · Portal'));

        return $this->renderTemplate('portal', [
            'accounts' => $module->accountRepository()->forOrganization($this->organizationUid()),
            'contacts' => $this->contactRepository()->findAll($this->organizationUid(), limit: 250),
            'selected' => $selected,
            'contact' => $contact,
            'quotationRows' => $quotationRows,
            'invoiceRows' => $invoiceRows,
            'verification' => is_array($verification) ? $verification : null,
        ]);
    }

    public function ___executePortalAccount(): void
    {
        $this->requirePost();
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $contactUid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('contact_uid')
        );
        $contact = $this->contactRepository()->require($contactUid);
        $this->requireSameOrganization($contact->organizationId);
        $email = strtolower(trim((string) $this->wire()->input->post('email')));
        $password = (string) $this->wire()->input->post('password');
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 12) {
            throw new WireException($this->_('A valid email and password of at least 12 characters are required.'));
        }
        if ($this->portalModule()->accountRepository()->findByEmail(
            $this->organizationUid(),
            $email,
        ) !== null) {
            throw new WireException($this->_('A portal account already uses this email address.'));
        }
        $account = $this->portalModule()->authentication()->register(
            $this->organizationUid(),
            $contactUid,
            $email,
            $password,
            (int) $this->wire()->user->id,
        );
        $this->audit('portal', 'account', $account->uid->toString(), 'created');
        $this->message($this->_('Portal account created.'));
        $this->wire()->session->redirect(
            '../portal/?id=' . rawurlencode($account->uid->toString())
        );
    }

    public function ___executePortalVerify(): void
    {
        $this->requirePost();
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $email = strtolower(trim((string) $this->wire()->input->post('email')));
        $password = (string) $this->wire()->input->post('password');
        $accountUid = '';
        try {
            $account = $this->portalModule()->authentication()->authenticate(
                $this->organizationUid(),
                $email,
                $password,
            );
            $accountUid = $account->uid->toString();
            $this->wire()->session->set('kontorPortalVerification', [
                'ok' => true,
                'message' => $this->_('Credentials accepted; login timestamp recorded.'),
            ]);
            $this->audit('portal', 'account', $accountUid, 'login_verified');
        } catch (\Kontor\Portal\Application\PortalAuthenticationFailedException) {
            $this->wire()->session->set('kontorPortalVerification', [
                'ok' => false,
                'message' => $this->_('Credentials rejected.'),
            ]);
        }
        $redirect = '../portal/';
        if ($accountUid !== '') {
            $redirect .= '?id=' . rawurlencode($accountUid);
        }
        $this->wire()->session->redirect($redirect);
    }

    public function ___executePortalStatus(): void
    {
        $this->requirePost();
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('account_uid')
        );
        $action = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('action')
        );
        $this->requireAction($action, ['enable', 'disable']);
        $account = $this->portalModule()->accountRepository()->require($uid);
        $this->requireSameOrganization($account->organizationId);
        $action === 'enable' ? $account->enable() : $account->disable();
        $this->portalModule()->accountRepository()->save($account);
        $this->audit('portal', 'account', $uid, $action . 'd');
        $this->message($action === 'enable'
            ? $this->_('Portal account enabled.')
            : $this->_('Portal account disabled.'));
        $this->wire()->session->redirect('../portal/?id=' . rawurlencode($uid));
    }

    public function ___executePortalProfile(): void
    {
        $this->requirePost();
        $this->requirePortal();
        $this->requirePermission('kontor-portal-account-manage');
        $uid = $this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('account_uid')
        );
        $account = $this->portalModule()->accountRepository()->require($uid);
        $this->requireSameOrganization($account->organizationId);
        $changes = [];
        foreach (['firstName', 'middleName', 'lastName', 'phone', 'mobile', 'preferredLanguage'] as $field) {
            $changes[$field] = trim($this->wire()->sanitizer->text(
                (string) $this->wire()->input->post($field)
            ));
        }
        $contact = $this->portalModule()->profile()->update(
            $account->organizationId,
            $account->contactUid,
            $changes,
        );
        $this->audit('portal', 'profile', $contact->uid->toString(), 'updated');
        $this->message($this->_('Customer-safe profile fields updated.'));
        $this->wire()->session->redirect('../portal/?id=' . rawurlencode($uid));
    }

    public function ___executeFiles(): string
    {
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-view');
        $module = $this->filesModule();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $selected = $id !== '' ? $module->fileRepository()->find($id) : null;
        if ($id !== '' && ($selected === null
            || (int) $selected['organization_id'] !== $this->organizationInternalId())) {
            throw new Wire404Exception($this->_('File metadata was not found.'));
        }
        $versions = [];
        if ($selected !== null && $selected['entity_type'] !== null && $selected['entity_uid'] !== null) {
            $versions = $module->fileManager()->versionHistory(
                (string) $selected['entity_type'],
                (string) $selected['entity_uid'],
                (string) $selected['original_name'],
                $this->organizationUid(),
            );
        }
        $shareResult = $this->wire()->session->get('kontorFilesShareResult');
        $this->wire()->session->set('kontorFilesShareResult', null);
        $allFiles = $module->fileRepository()->forOrganization($this->organizationInternalId());
        $query = trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q')));
        $classification = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('classification'),
            ['general', 'financial', 'confidential', 'restricted']
        );
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['active', 'archived']
        );
        $files = array_values(array_filter(
            $allFiles,
            static function (array $file) use ($query, $classification, $status): bool {
                if ($query !== '' && stripos((string) $file['original_name'], $query) === false) {
                    return false;
                }
                if ($classification !== null && $classification !== ''
                    && (string) $file['classification'] !== $classification) {
                    return false;
                }
                if ($status === 'active' && $file['archived_at'] !== null) {
                    return false;
                }
                if ($status === 'archived' && $file['archived_at'] === null) {
                    return false;
                }

                return true;
            }
        ));
        $entityRoutes = array_filter([
            'contact' => $this->contactsReady() && $this->can('kontor-contacts-contact-view') ? 'contact/' : null,
            'company' => $this->contactsReady() && $this->can('kontor-contacts-company-view') ? 'company/' : null,
            'lead' => $this->crmReady() && $this->can('kontor-crm-lead-view') ? 'crm-lead/' : null,
            'deal' => $this->crmReady() && $this->can('kontor-crm-deal-view') ? 'crm-deal/' : null,
            'task' => $this->tasksReady() && $this->can('kontor-tasks-task-view') ? 'task/' : null,
            'project' => $this->projectsReady() && $this->can('kontor-projects-project-view') ? 'project/' : null,
            'catalog_item' => $this->catalogReady() && $this->can('kontor-catalog-item-view') ? 'catalog-item/' : null,
            'invoice' => $this->invoicesReady() && $this->can('kontor-invoices-invoice-view') ? 'invoice/' : null,
            'quotation' => $this->salesReady() && $this->can('kontor-sales-quotation-view') ? 'quotation/' : null,
            'document_template' => $this->documentsReady() && $this->can('kontor-documents-template-view') ? 'documents/' : null,
        ]);
        $this->setPageTitle($selected === null
            ? $this->_('Kontor · Files')
            : sprintf($this->_('Kontor · %s'), (string) $selected['original_name']));

        return $this->renderTemplate('files', [
            'files' => $files,
            'allFiles' => $allFiles,
            'selected' => $selected,
            'versions' => $versions,
            'shareResult' => is_array($shareResult) ? $shareResult : null,
            'storageHealth' => $module->healthCheck()->run(),
            'query' => $query,
            'selectedClassification' => $classification,
            'selectedStatus' => $status,
            'entityRoutes' => $entityRoutes,
            'canUpload' => $this->can('kontor-files-file-upload'),
            'canDownload' => $this->can('kontor-files-file-download'),
            'canShare' => $this->can('kontor-files-file-share'),
            'canDelete' => $this->can('kontor-files-file-delete'),
        ]);
    }

    public function ___executeFilesUpload(): void
    {
        $this->requirePost();
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-upload');
        $upload = $_FILES['file_upload'] ?? null;
        if (!is_array($upload)
            || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_uploaded_file((string) ($upload['tmp_name'] ?? ''))) {
            throw new WireException($this->_('Choose a file that completed uploading.'));
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($size < 1 || $size > 25 * 1024 * 1024) {
            throw new WireException($this->_('Files must be between 1 byte and 25 MB.'));
        }
        $originalName = basename(str_replace('\\', '/', (string) ($upload['name'] ?? '')));
        if ($originalName === '' || $originalName === '.' || strlen($originalName) > 255) {
            throw new WireException($this->_('The original filename is invalid or too long.'));
        }
        $visibility = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('visibility')
        ));
        $classification = strtolower($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('classification')
        ));
        $this->requireAction($visibility, ['private', 'internal']);
        $this->requireAction($classification, ['general', 'financial', 'confidential', 'restricted']);
        $entityType = strtolower(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('entity_type')
        )));
        $entityUid = strtoupper(trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('entity_uid')
        )));
        if (($entityType === '') !== ($entityUid === '')) {
            throw new WireException($this->_('Entity type and entity UID must be provided together.'));
        }
        if ($entityType !== ''
            && (preg_match('/^[a-z][a-z0-9_-]{0,49}$/', $entityType) !== 1
                || !Uid::isValid($entityUid))) {
            throw new WireException($this->_('Use a valid entity type and ULID.'));
        }
        $stream = fopen((string) $upload['tmp_name'], 'rb');
        if ($stream === false) {
            throw new WireException($this->_('The uploaded file could not be opened.'));
        }
        try {
            $result = $this->filesModule()->fileManager()->upload(
                organizationUid: $this->organizationUid(),
                originalName: $originalName,
                contents: $stream,
                visibility: $visibility,
                classification: $classification,
                entityType: $entityType !== '' ? $entityType : null,
                entityUid: $entityUid !== '' ? $entityUid : null,
                metadata: $this->fileMetadataFromPost(),
                actorId: (int) $this->wire()->user->id,
            );
        } finally {
            fclose($stream);
        }
        $this->audit('files', 'file', $result['uid'], 'uploaded', metadata: [
            'version' => $result['versionNumber'],
            'visibility' => $visibility,
            'classification' => $classification,
            'entityType' => $entityType !== '' ? $entityType : null,
        ]);
        $this->message(sprintf($this->_('File version %d uploaded.'), $result['versionNumber']));
        $this->wire()->session->redirect('../files/?id=' . rawurlencode($result['uid']));
    }

    public function ___executeFilesAction(): void
    {
        $this->requirePost();
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-delete');
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('file_uid'));
        $action = $this->wire()->sanitizer->text((string) $this->wire()->input->post('action'));
        $this->requireAction($action, ['archive', 'restore']);
        $manager = $this->filesModule()->fileManager();
        $action === 'archive'
            ? $manager->archive($uid, $this->organizationUid())
            : $manager->restore($uid, $this->organizationUid());
        $this->audit('files', 'file', $uid, $action . 'd');
        $this->message($action === 'archive'
            ? $this->_('File archived; its bytes remain in private storage.')
            : $this->_('File restored as the active version.'));
        $this->wire()->session->redirect('../files/?id=' . rawurlencode($uid));
    }

    public function ___executeFilesShare(): void
    {
        $this->requirePost();
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-share');
        $uid = $this->wire()->sanitizer->text((string) $this->wire()->input->post('file_uid'));
        $file = $this->filesModule()->fileRepository()->find($uid);
        if ($file === null
            || (int) $file['organization_id'] !== $this->organizationInternalId()
            || $file['archived_at'] !== null) {
            throw new WireException($this->_('Only an active file in this organization can be shared.'));
        }
        $expiresAt = new \DateTimeImmutable('+15 minutes');
        $url = $this->filesModule()->fileManager()->temporaryUrl(
            $uid,
            $expiresAt,
            $this->organizationUid(),
        );
        $this->wire()->session->set('kontorFilesShareResult', [
            'uid' => $uid,
            'url' => $url,
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ]);
        $this->audit('files', 'file', $uid, 'shared', metadata: [
            'expiresAt' => $expiresAt->format(DATE_ATOM),
        ]);
        $this->message($this->_('Signed download link generated for 15 minutes.'));
        $this->wire()->session->redirect('../files/?id=' . rawurlencode($uid));
    }

    public function ___executeFilesDownload(): never
    {
        $this->requireFiles();
        $this->requirePermission('kontor-files-file-download');
        $path = (string) $this->wire()->input->get('path');
        $expires = (int) $this->wire()->input->get('expires');
        $signature = (string) $this->wire()->input->get('signature');
        if ($path === '' || $expires < 1 || preg_match('/^[a-f0-9]{64}$/', $signature) !== 1
            || !$this->filesModule()->verifyTemporaryUrl($path, $expires, $signature)) {
            throw new WirePermissionException($this->_('The signed file link is invalid or expired.'));
        }
        $file = $this->filesModule()->fileRepository()->findByPath(
            $this->organizationInternalId(),
            $path,
        );
        if ($file === null || $file['archived_at'] !== null) {
            throw new Wire404Exception($this->_('The active file was not found.'));
        }
        $stream = $this->filesModule()->fileManager()->read(
            (string) $file['uid'],
            $this->organizationUid(),
        );
        $downloadName = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $file['original_name']) ?: 'download';
        header('Content-Type: ' . ((string) ($file['mime_type'] ?? '') ?: 'application/octet-stream'));
        header('Content-Length: ' . (int) $file['size_bytes']);
        header('Content-Disposition: attachment; filename="' . $downloadName . '"');
        header('Cache-Control: private, no-store');
        fpassthru($stream);
        fclose($stream);
        exit;
    }
}
