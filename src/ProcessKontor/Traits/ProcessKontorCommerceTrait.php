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
trait ProcessKontorCommerceTrait
{

    public function ___executeSales(): string
    {
        $this->requireSales();
        $this->requirePermission('kontor-sales-quotation-view');
        $this->setPageTitle($this->_('Kontor · Sales'));
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $canViewOrders = $this->can('kontor-sales-order-view');

        return $this->renderTemplate('sales', [
            'quotations' => $sales->quotationRepository()->findMatching(
                $this->organizationUid(),
                limit: 50,
            ),
            'orders' => $canViewOrders
                ? $sales->orderRepository()->findMatching($this->organizationUid(), limit: 50)
                : [],
            'customerLabels' => $this->salesCustomerLabels(),
            'canCreateQuotation' => $this->can('kontor-sales-quotation-create'),
            'canViewOrders' => $canViewOrders,
            'showCatalog' => $this->catalogReady() && $this->can('kontor-catalog-item-view'),
            'showInvoices' => $this->invoicesReady() && $this->can('kontor-invoices-invoice-view'),
        ]);
    }

    public function ___executeSalesQuotation(): string
    {
        $this->requireSales();
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $quotation = $id !== '' ? $sales->quotationRepository()->require($id) : null;

        if ($quotation !== null) {
            $this->requirePermission('kontor-sales-quotation-view');
            $this->requireSameOrganization($quotation->organizationId);
        } else {
            $this->requirePermission('kontor-sales-quotation-create');
        }

        $sourceDeal = null;
        $sourceDealError = '';
        if ($quotation !== null && $quotation->dealUid !== null
            && $this->crmReady() && $this->can('kontor-crm-deal-view')) {
            /** @var KontorCRM $crm */
            $crm = $this->wire()->modules->get('KontorCRM');
            $candidate = $crm->dealRepository()->find($quotation->dealUid);
            if ($candidate !== null && hash_equals($this->organizationUid(), $candidate->organizationId)) {
                $sourceDeal = $candidate;
            }
        } elseif ($quotation === null) {
            $dealUid = $this->wire()->sanitizer->text((string) (
                $this->wire()->input->post('deal_uid') ?: $this->wire()->input->get('deal')
            ));
            if ($dealUid !== '') {
                $this->requireCrm();
                $this->requirePermission('kontor-crm-deal-view');
                /** @var KontorCRM $crm */
                $crm = $this->wire()->modules->get('KontorCRM');
                $sourceDeal = $crm->dealRepository()->require($dealUid);
                $this->requireSameOrganization($sourceDeal->organizationId);
                if ($sourceDeal->status !== 'won') {
                    $sourceDealError = $this->_('Only won deals can become quotations.');
                } elseif ($sourceDeal->companyUid === null && $sourceDeal->contactUid === null) {
                    $sourceDealError = $this->_('Add a contact or company to the won deal first.');
                }
            }
        }

        $existingDealQuotations = $quotation === null && $sourceDeal !== null
            ? $sales->quotationRepository()->forDeal(
                $this->organizationUid(),
                $sourceDeal->uid->toString(),
            )
            : [];
        if ($existingDealQuotations !== []) {
            $sourceDealError = '';
        }
        if ($this->wire()->input->post('submit_save') && $existingDealQuotations !== []) {
            $this->requirePost();
            $existingQuotation = $existingDealQuotations[0];
            $this->message($this->_('This deal already has a quotation.'));
            $this->wire()->session->redirect($this->can('kontor-sales-quotation-view')
                ? '../sales-quotation/?id=' . rawurlencode($existingQuotation->uid->toString())
                : '../crm-deal/?id=' . rawurlencode($sourceDeal->uid->toString()));
        }

        $supportedLanguages = ['en', 'de', 'fr', 'es'];
        $defaultLanguage = strtolower($this->organization()->defaultLanguage);
        if (!in_array($defaultLanguage, $supportedLanguages, true)) {
            $defaultLanguage = 'en';
        }
        $values = [
            'customer' => $sourceDeal?->companyUid !== null
                ? 'company:' . $sourceDeal->companyUid
                : ($sourceDeal?->contactUid !== null ? 'contact:' . $sourceDeal->contactUid : ''),
            'currency' => $sourceDeal?->value?->currencyCode() ?? $this->organization()->defaultCurrency,
            'validUntil' => '',
            'language' => $defaultLanguage,
            'lineTitle' => $sourceDeal?->title ?? '',
            'quantity' => '1',
            'unitCode' => $sourceDeal !== null ? 'project' : 'pcs',
            'unitPrice' => $sourceDeal?->value !== null
                ? number_format($sourceDeal->value->amountMinor() / 100, 2, '.', '')
                : '',
            'taxRate' => '0',
        ];
        $error = $sourceDealError;

        if ($quotation === null && $this->wire()->input->post('submit_save')) {
            $this->requirePost();
            $values = [
                'customer' => $this->wire()->sanitizer->text((string) $this->wire()->input->post('customer')),
                'currency' => strtoupper($this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('currency')
                )),
                'validUntil' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('valid_until')
                ),
                'language' => $this->wire()->sanitizer->option(
                    (string) $this->wire()->input->post('language'),
                    ['en', 'de', 'fr', 'es']
                ) ?? 'en',
                'lineTitle' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('line_title')
                ),
                'quantity' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('quantity')
                ),
                'unitCode' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('unit_code')
                ),
                'unitPrice' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('unit_price')
                ),
                'taxRate' => $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('tax_rate')
                ),
            ];
            if ($sourceDeal !== null) {
                $values['customer'] = $sourceDeal->companyUid !== null
                    ? 'company:' . $sourceDeal->companyUid
                    : 'contact:' . $sourceDeal->contactUid;
            }
            [$customerType, $customerUid] = array_pad(explode(':', $values['customer'], 2), 2, '');

            if ($error !== '') {
                // Keep the source-deal validation error.
            } elseif (!isset($this->salesCustomerLabels()[$values['customer']])) {
                $error = $this->_('Select a valid customer.');
            } elseif (preg_match('/^[A-Z]{3}$/', $values['currency']) !== 1) {
                $error = $this->_('Currency must be a three-letter code.');
            } elseif ($values['lineTitle'] === '') {
                $error = $this->_('The first line title is required.');
            } elseif (!is_numeric($values['quantity']) || (float) $values['quantity'] <= 0) {
                $error = $this->_('Quantity must be greater than zero.');
            } elseif (!is_numeric(str_replace(',', '.', $values['unitPrice']))
                || (float) str_replace(',', '.', $values['unitPrice']) < 0) {
                $error = $this->_('Unit price must be zero or greater.');
            } elseif (!is_numeric($values['taxRate'])
                || (float) $values['taxRate'] < 0
                || (float) $values['taxRate'] > 100) {
                $error = $this->_('Tax rate must be between 0 and 100.');
            } elseif ($values['unitCode'] === '') {
                $error = $this->_('Unit code is required.');
            }

            $validUntil = null;
            if ($error === '' && $values['validUntil'] !== '') {
                try {
                    $validUntil = new \DateTimeImmutable($values['validUntil']);
                } catch (\Throwable) {
                    $error = $this->_('Valid-until date is invalid.');
                }
            }

            if ($error === '') {
                $quotation = Quotation::create(
                    $this->organizationUid(),
                    $customerType,
                    $customerUid,
                    $values['currency'],
                    contactUid: $sourceDeal?->contactUid
                        ?? ($customerType === 'contact' ? $customerUid : null),
                    dealUid: $sourceDeal?->uid->toString(),
                    validUntil: $validUntil,
                    documentLanguage: $values['language'],
                );
                $line = DocumentLine::create(
                    $this->organizationUid(),
                    'quotation',
                    $quotation->uid->toString(),
                    $values['lineTitle'],
                    (float) $values['quantity'],
                    Money::ofMinor(
                        (int) round((float) str_replace(',', '.', $values['unitPrice']) * 100),
                        $values['currency']
                    ),
                    unitCode: $values['unitCode'],
                    taxRate: (float) $values['taxRate'],
                );
                $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
                $pdo->beginTransaction();
                try {
                    $sales->quotationRepository()->save($quotation);
                    $sales->documentLineRepository()->save($line);
                    $quotation->applyTotalsFromLines([$line]);
                    $sales->quotationRepository()->save($quotation);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                $this->audit(
                    'sales',
                    'quotation',
                    $quotation->uid->toString(),
                    'created',
                    current: [
                        'customerType' => $quotation->customerType,
                        'customerUid' => $quotation->customerUid,
                        'dealUid' => $quotation->dealUid,
                        'totalMinor' => $quotation->total->amountMinor(),
                    ],
                );
                $this->message($this->_('Quotation draft created.'));
                $this->wire()->session->redirect(
                    '../sales-quotation/?id=' . rawurlencode($quotation->uid->toString())
                );
            }
        }

        $lines = $quotation !== null
            ? $sales->documentLineRepository()->forDocument('quotation', $quotation->uid->toString())
            : [];
        $existingOrder = $quotation !== null
            ? $sales->orderRepository()->findByQuotation($quotation->uid->toString())
            : null;
        $quotationTemplate = null;
        if ($quotation !== null && $this->documentsReady()) {
            $quotationTemplate = $quotation->templateUid !== null
                ? $this->documentsModule()->templateRepository()->find($quotation->templateUid)
                : $this->documentsModule()->templateRepository()->findCurrentVersion(
                    $quotation->organizationId,
                    'quotation.standard',
                    $quotation->documentLanguage,
                );
        }
        $issuedFiles = $quotation !== null && $this->filesReady()
            ? $this->filesModule()->fileManager()->forEntity(
                'quotation',
                $quotation->uid->toString(),
                $quotation->organizationId,
            )
            : [];
        $this->setPageTitle($quotation === null
            ? ($existingDealQuotations !== []
                ? $this->_('Kontor · Quotation already exists')
                : $this->_('Kontor · New quotation'))
            : sprintf($this->_('Kontor · %s'), $quotation->number ?? $this->_('Draft quotation')));

        return $this->renderTemplate('sales-quotation', [
            'quotation' => $quotation,
            'lines' => $lines,
            'existingOrder' => $existingOrder,
            'quotationTemplate' => $quotationTemplate,
            'issuedFile' => $issuedFiles[0] ?? null,
            'values' => $values,
            'customers' => $this->salesCustomerLabels(),
            'contactsReady' => $this->contactsReady(),
            'canCreateContact' => $this->contactsReady() && $this->can('kontor-contacts-contact-create'),
            'canCreateCompany' => $this->contactsReady() && $this->can('kontor-contacts-company-create'),
            'languageOptions' => [
                'en' => $this->_('English'),
                'de' => $this->_('German'),
                'fr' => $this->_('French'),
                'es' => $this->_('Spanish'),
            ],
            'sourceDeal' => $sourceDeal,
            'existingDealQuotations' => $existingDealQuotations,
            'canViewExistingQuotation' => $this->can('kontor-sales-quotation-view'),
            'mailReady' => $this->mailReady(),
            'mailboxes' => $quotation !== null && $this->mailReady()
                ? array_values(array_filter(
                    $this->mailModule()->mailboxRepository()->forOrganization($quotation->organizationId),
                    static fn (\Kontor\Mail\Domain\Mailbox $mailbox): bool => $mailbox->isActive(),
                ))
                : [],
            'customerEmail' => $quotation !== null
                ? $this->quotationCustomerEmail($quotation)
                : '',
            'canIssue' => $this->can('kontor-sales-quotation-issue'),
            'canSend' => $this->can('kontor-sales-quotation-send'),
            'canAccept' => $this->can('kontor-sales-quotation-accept'),
            'canCancel' => $this->can('kontor-sales-quotation-cancel'),
            'canCreateOrder' => $this->can('kontor-sales-order-create'),
            'canViewOrder' => $this->can('kontor-sales-order-view'),
            'documentsReady' => $this->documentsReady(),
            'filesReady' => $this->filesReady(),
            'showDocuments' => $this->documentsReady() && $this->can('kontor-documents-template-view'),
            'showFiles' => $this->filesReady() && $this->can('kontor-files-file-view'),
            'canManageMailboxes' => $this->mailReady() && $this->can('kontor-mail-mailbox-manage'),
            'error' => $error,
        ]);
    }

    public function ___executeSalesQuotationAction(): void
    {
        $this->requirePost();
        $this->requireSales();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['issue', 'send', 'accept', 'convert', 'cancel', 'archive', 'restore']
        );
        $this->requireAction($action, ['issue', 'send', 'accept', 'convert', 'cancel', 'archive', 'restore']);
        $permissions = [
            'issue' => 'kontor-sales-quotation-issue',
            'send' => 'kontor-sales-quotation-send',
            'accept' => 'kontor-sales-quotation-accept',
            'convert' => 'kontor-sales-order-create',
            'cancel' => 'kontor-sales-quotation-cancel',
            'archive' => 'kontor-sales-quotation-edit',
            'restore' => 'kontor-sales-quotation-edit',
        ];
        $this->requirePermission($permissions[$action]);
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $quotation = $sales->quotationRepository()->require($id);
        $this->requireSameOrganization($quotation->organizationId);

        if ($action === 'issue') {
            $this->requireDocuments();
            $this->requireFiles();
            $template = $this->documentsModule()->templateRepository()->findCurrentVersion(
                $quotation->organizationId,
                'quotation.standard',
                $quotation->documentLanguage,
            );
            if ($template === null) {
                throw new WireException($this->_(
                    'Publish an active quotation.standard document template before issuing this quotation.'
                ));
            }

            $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
            $pdo->beginTransaction();
            try {
                $issued = $sales->quotationWorkflow()->issue($id);
                $lines = $sales->documentLineRepository()->forDocument('quotation', $id);
                $data = $this->quotationDocumentData($issued, $lines);
                $snapshot = $this->documentsModule()->snapshotBuilder()->build($template, $data);
                $pdf = $this->documentsModule()->renderService()->renderPdf($template, $data);
                $issued->attachIssuedDocument($template->uid->toString(), $snapshot);
                $sales->quotationRepository()->save($issued);
                $stored = $this->filesModule()->fileManager()->upload(
                    organizationUid: $issued->organizationId,
                    originalName: ($issued->number ?? 'quotation-' . $id) . '.pdf',
                    contents: $pdf,
                    visibility: 'private',
                    classification: 'confidential',
                    entityType: 'quotation',
                    entityUid: $id,
                    metadata: [
                        'source' => 'sales',
                        'documentType' => 'quotation',
                        'number' => $issued->number,
                        'documentSnapshot' => $snapshot,
                    ],
                    actorId: (int) $this->wire()->user->id,
                );
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            $this->audit('files', 'file', $stored['uid'], 'generated', metadata: [
                'sourceComponent' => 'sales',
                'quotationUid' => $id,
                'version' => $stored['versionNumber'],
            ]);
            $this->message($this->_('Quotation issued with an immutable snapshot and private PDF.'));
        } elseif ($action === 'send') {
            $this->requireMail();
            $mailboxUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('mailbox_uid')
            );
            $recipient = strtolower(trim((string) $this->wire()->input->post('recipient')));
            $mailbox = $this->mailModule()->mailboxRepository()->require($mailboxUid);
            $this->requireSameOrganization($mailbox->organizationId);
            if (!$mailbox->isActive()) {
                throw new WireException($this->_('Select an active mailbox.'));
            }
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new WireException($this->_('A valid quotation recipient is required.'));
            }
            $number = $quotation->number ?? $this->_('Quotation');
            $subject = sprintf($this->_('Quotation %s'), $number);
            $body = sprintf(
                $this->_("Hello,\n\nplease find quotation %s for %s %s. The offer is valid until %s.\n\nRegards"),
                $number,
                number_format($quotation->total->amountMinor() / 100, 2, '.', ''),
                $quotation->total->currencyCode(),
                $quotation->validUntil?->format('Y-m-d') ?? $this->_('further notice'),
            );
            $dryRun = (bool) $this->wire()->input->post('dry_run');
            $outbound = $dryRun
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
            $message = $outbound->send(
                $quotation->organizationId,
                $mailbox->uid->toString(),
                $mailbox->emailAddress,
                [$recipient],
                [],
                $subject,
                $body,
                (int) $this->wire()->user->id,
            );
            $this->mailModule()->entityLinking()->link(
                $quotation->organizationId,
                $message->uid->toString(),
                'quotation',
                $quotation->uid->toString(),
                (int) $this->wire()->user->id,
            );
            $this->audit('mail', 'message', $message->uid->toString(), 'quotation_delivery', metadata: [
                'quotationUid' => $quotation->uid->toString(),
                'dryRun' => $dryRun,
                'status' => $message->status,
            ]);
            if ($message->status !== 'sent') {
                throw new WireException(sprintf(
                    $this->_('Quotation delivery failed: %s'),
                    $message->error ?? $this->_('unknown transport error'),
                ));
            }
            $sales->quotationWorkflow()->send($id);
            $this->audit('sales', 'quotation', $id, 'send', metadata: [
                'mailMessageUid' => $message->uid->toString(),
                'recipient' => $recipient,
                'dryRun' => $dryRun,
            ]);
            $this->message($dryRun
                ? $this->_('Quotation delivery simulated, recorded in Mail, and marked sent.')
                : $this->_('Quotation sent, recorded in Mail, and marked sent.'));
            $this->wire()->session->redirect('../sales-quotation/?id=' . rawurlencode($id));
            return;
        } elseif ($action === 'accept') {
            $sales->quotationWorkflow()->accept($id);
        } elseif ($action === 'convert') {
            $order = $sales->conversionService()->convert($id);
            $this->audit(
                'sales',
                'order',
                $order->uid->toString(),
                'created',
                current: ['quotationUid' => $id, 'number' => $order->number],
            );
            $this->message($this->_('Sales order created.'));
            $this->wire()->session->redirect(
                '../sales-order/?id=' . rawurlencode($order->uid->toString())
            );
        } elseif ($action === 'cancel') {
            $sales->quotationWorkflow()->cancel($id);
        } elseif ($action === 'restore') {
            $sales->quotationRepository()->restore($id);
        } else {
            $sales->quotationRepository()->archive($id);
        }

        $this->audit('sales', 'quotation', $id, $action);
        $this->message($this->_('Quotation updated.'));
        $this->wire()->session->redirect('../sales-quotation/?id=' . rawurlencode($id));
    }

    public function ___executeSalesOrder(): string
    {
        $this->requireSales();
        $this->requirePermission('kontor-sales-order-view');
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $order = $sales->orderRepository()->require($id);
        $this->requireSameOrganization($order->organizationId);
        $lines = $sales->documentLineRepository()->forDocument('order', $id);
        $trackedLines = $this->salesOrderTrackedLines($lines);
        $reservationMovements = $order->isConfirmed()
            ? $this->salesOrderReservationMovements($id)
            : [];
        $reservationWarehouseUid = $this->reservationWarehouseUid($reservationMovements);
        $reservationWarehouseLabel = null;
        if ($reservationWarehouseUid !== null && $this->inventoryReady()) {
            $warehouse = $this->inventoryModule()->warehouseRepository()->find($reservationWarehouseUid);
            if ($warehouse !== null && hash_equals($order->organizationId, $warehouse->organizationId)) {
                $reservationWarehouseLabel = $warehouse->code . ' · ' . $warehouse->name;
            }
        }
        $sourceQuotation = null;
        if ($order->quotationUid !== null && $this->can('kontor-sales-quotation-view')) {
            $candidate = $sales->quotationRepository()->find($order->quotationUid);
            if ($candidate !== null && hash_equals($order->organizationId, $candidate->organizationId)) {
                $sourceQuotation = $candidate;
            }
        }
        $this->setPageTitle(sprintf($this->_('Kontor · %s'), $order->number ?? $this->_('Sales order')));

        return $this->renderTemplate('sales-order', [
            'order' => $order,
            'lines' => $lines,
            'customerLabel' => $this->salesCustomerLabels()[
                $order->customerType . ':' . $order->customerUid
            ] ?? $this->_('Customer unavailable'),
            'sourceQuotation' => $sourceQuotation,
            'existingInvoice' => $this->invoicesReady()
                ? $this->invoiceModule()->invoiceRepository()->findByOrder($id)
                : null,
            'invoicesReady' => $this->invoicesReady(),
            'inventoryWarehouses' => $order->isPending() && $trackedLines !== [] && $this->inventoryReady()
                ? array_values(array_filter(
                    $this->inventoryModule()->warehouseRepository()->forOrganization(
                        $this->organizationUid()
                    ),
                    static fn (Warehouse $warehouse): bool => $warehouse->isActive(),
                ))
                : [],
            'trackedLineCount' => count($trackedLines),
            'reservationWarehouseUid' => $reservationWarehouseUid,
            'reservationWarehouseLabel' => $reservationWarehouseLabel,
            'inventoryReady' => $this->inventoryReady(),
            'showInventory' => $this->inventoryReady()
                && $this->can('kontor-inventory-stock-view'),
            'canReserveInventory' => $this->inventoryReady()
                && $this->can('kontor-inventory-reserve'),
            'canShipInventory' => $this->inventoryReady()
                && $this->can('kontor-inventory-adjust'),
            'canReleaseInventory' => $this->inventoryReady()
                && $this->can('kontor-inventory-release'),
            'canConfirm' => $this->can('kontor-sales-order-confirm'),
            'canComplete' => $this->can('kontor-sales-order-complete'),
            'canCancel' => $this->can('kontor-sales-order-cancel'),
            'canCreateInvoice' => $this->invoicesReady()
                && $this->can('kontor-invoices-invoice-create'),
            'canViewInvoice' => $this->invoicesReady()
                && $this->can('kontor-invoices-invoice-view'),
        ]);
    }

    public function ___executeSalesOrderAction(): void
    {
        $this->requirePost();
        $this->requireSales();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['confirm', 'complete', 'cancel', 'archive', 'restore']
        );
        $this->requireAction($action, ['confirm', 'complete', 'cancel', 'archive', 'restore']);
        $permissions = [
            'confirm' => 'kontor-sales-order-confirm',
            'complete' => 'kontor-sales-order-complete',
            'cancel' => 'kontor-sales-order-cancel',
            'archive' => 'kontor-sales-order-edit',
            'restore' => 'kontor-sales-order-edit',
        ];
        $this->requirePermission($permissions[$action]);
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $order = $sales->orderRepository()->require($id);
        $this->requireSameOrganization($order->organizationId);
        $lines = $sales->documentLineRepository()->forDocument('order', $id);
        $trackedLines = $this->salesOrderTrackedLines($lines);
        $reservations = $this->salesOrderReservationMovements($id);

        if ($action === 'confirm') {
            if ($trackedLines === []) {
                $sales->orderWorkflow()->confirm($id);
            } else {
                $this->requireInventory();
                $this->requirePermission('kontor-inventory-reserve');
                $warehouseUid = $this->wire()->sanitizer->text(
                    (string) $this->wire()->input->post('warehouse_uid')
                );
                $warehouse = $this->inventoryModule()->warehouseRepository()->require($warehouseUid);
                $this->requireSameOrganization($warehouse->organizationId);
                if (!$warehouse->isActive()) {
                    throw new WireException($this->_('Select an active fulfillment warehouse.'));
                }

                $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
                $pdo->beginTransaction();
                try {
                    foreach ($trackedLines as $line) {
                        $this->inventoryModule()->movements()->reserve(
                            $order->organizationId,
                            $warehouseUid,
                            (string) $line->itemUid,
                            $line->quantity,
                            referenceType: 'sales_order',
                            referenceUid: $id,
                            unitCode: $line->unitCode,
                            idempotencyKey: 'sales-order:' . $id . ':reserve:' . $line->uid->toString(),
                            createdBy: (int) $this->wire()->user->id,
                        );
                    }
                    $sales->orderWorkflow()->confirm($id);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                $this->message($this->_('Inventory reserved for the sales order.'));
            }
        } elseif ($action === 'complete') {
            if ($reservations === []) {
                $sales->orderWorkflow()->complete($id);
            } else {
                $this->requireInventory();
                $this->requirePermission('kontor-inventory-adjust');
                $warehouseUid = $this->reservationWarehouseUid($reservations);
                if ($warehouseUid === null) {
                    throw new WireException($this->_('The order has no consistent reservation warehouse.'));
                }

                $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
                $pdo->beginTransaction();
                try {
                    foreach ($reservations as $reservation) {
                        $this->inventoryModule()->movements()->shipReserved(
                            $order->organizationId,
                            $warehouseUid,
                            $reservation->itemUid,
                            $reservation->quantity,
                            referenceType: 'sales_order',
                            referenceUid: $id,
                            unitCode: $reservation->unitCode,
                            idempotencyKey: 'sales-order:' . $id . ':ship:' . $reservation->uid->toString(),
                            createdBy: (int) $this->wire()->user->id,
                        );
                    }
                    $sales->orderWorkflow()->complete($id);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                $this->message($this->_('Reserved inventory shipped and order completed.'));
            }
        } elseif ($action === 'cancel') {
            if ($reservations === []) {
                $sales->orderWorkflow()->cancel($id);
            } else {
                $this->requireInventory();
                $this->requirePermission('kontor-inventory-release');
                $warehouseUid = $this->reservationWarehouseUid($reservations);
                if ($warehouseUid === null) {
                    throw new WireException($this->_('The order has no consistent reservation warehouse.'));
                }

                $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
                $pdo->beginTransaction();
                try {
                    foreach ($reservations as $reservation) {
                        $this->inventoryModule()->movements()->release(
                            $order->organizationId,
                            $warehouseUid,
                            $reservation->itemUid,
                            $reservation->quantity,
                            referenceType: 'sales_order',
                            referenceUid: $id,
                            unitCode: $reservation->unitCode,
                            idempotencyKey: 'sales-order:' . $id . ':release:' . $reservation->uid->toString(),
                            createdBy: (int) $this->wire()->user->id,
                        );
                    }
                    $sales->orderWorkflow()->cancel($id);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
                $this->message($this->_('Inventory reservation released.'));
            }
        } elseif ($action === 'restore') {
            $sales->orderRepository()->restore($id);
        } else {
            $sales->orderRepository()->archive($id);
        }

        $this->audit('sales', 'order', $id, $action);
        $this->message($this->_('Sales order updated.'));
        $this->wire()->session->redirect('../sales-order/?id=' . rawurlencode($id));
    }

    public function ___executeInvoices(): string
    {
        $this->requireInvoices();
        $this->requirePermission('kontor-invoices-invoice-view');
        $this->setPageTitle($this->_('Kontor · Invoices'));
        $query = trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q')));
        $selectedStatus = $this->wire()->sanitizer->text((string) $this->wire()->input->get('status'));
        $selectedKind = $this->wire()->sanitizer->text((string) $this->wire()->input->get('kind'));
        $selectedStatus = in_array($selectedStatus, [
            'draft', 'issued', 'sent', 'overdue', 'partially_paid', 'outstanding', 'paid', 'cancelled',
        ], true) ? $selectedStatus : '';
        $selectedKind = in_array($selectedKind, ['invoice', 'credit_note'], true) ? $selectedKind : '';
        $customerLabels = $this->salesCustomerLabels();
        $allInvoices = $this->invoiceModule()->invoiceRepository()->findMatching(
            $this->organizationUid(),
            limit: 250,
        );
        $today = new \DateTimeImmutable('today');
        $invoices = array_values(array_filter(
            $allInvoices,
            static function (Invoice $invoice) use (
                $query,
                $selectedStatus,
                $selectedKind,
                $customerLabels,
                $today,
            ): bool {
                if ($selectedKind !== '' && $invoice->kind !== $selectedKind) {
                    return false;
                }
                if ($selectedStatus === 'outstanding') {
                    if ($invoice->due->amountMinor() <= 0 || !in_array(
                        $invoice->status,
                        ['issued', 'sent', 'overdue', 'partially_paid'],
                        true,
                    )) {
                        return false;
                    }
                } elseif ($selectedStatus === 'overdue') {
                    $pastDue = $invoice->dueDate !== null
                        && $invoice->dueDate < $today
                        && !in_array($invoice->status, ['draft', 'paid', 'cancelled'], true);
                    if ($invoice->due->amountMinor() <= 0
                        || ($invoice->status !== 'overdue' && !$pastDue)) {
                        return false;
                    }
                } elseif ($selectedStatus !== '' && $invoice->status !== $selectedStatus) {
                    return false;
                }
                if ($query === '') {
                    return true;
                }
                $customer = $customerLabels[$invoice->customerType . ':' . $invoice->customerUid] ?? '';
                $haystack = mb_strtolower(($invoice->number ?? '') . ' ' . $customer);

                return str_contains($haystack, mb_strtolower($query));
            },
        ));

        return $this->renderTemplate('invoices', [
            'invoices' => $invoices,
            'allInvoices' => $allInvoices,
            'customerLabels' => $customerLabels,
            'query' => $query,
            'selectedStatus' => $selectedStatus,
            'selectedKind' => $selectedKind,
            'canViewSales' => $this->can('kontor-sales-order-view'),
        ]);
    }

    public function ___executeInvoice(): string
    {
        $this->requireInvoices();
        $this->requirePermission('kontor-invoices-invoice-view');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $module = $this->invoiceModule();
        $invoice = $module->invoiceRepository()->require($id);
        $this->requireSameOrganization($invoice->organizationId);
        $this->setPageTitle(sprintf(
            $this->_('Kontor · %s'),
            $invoice->number ?? $this->_('Draft invoice')
        ));
        $documentType = $invoice->kind === 'credit_note' ? 'credit_note' : 'invoice';
        $invoiceTemplate = null;
        $creditNoteTemplate = null;
        if ($this->documentsReady()) {
            $invoiceTemplate = $invoice->templateUid !== null
                ? $this->documentsModule()->templateRepository()->find($invoice->templateUid)
                : $this->documentsModule()->templateRepository()->findCurrentVersion(
                    $invoice->organizationId,
                    $documentType . '.standard',
                    $invoice->documentLanguage,
                );
            if ($invoice->kind === 'invoice' && $invoice->isCreditable()) {
                $creditNoteTemplate = $this->documentsModule()->templateRepository()->findCurrentVersion(
                    $invoice->organizationId,
                    'credit_note.standard',
                    $invoice->documentLanguage,
                );
            }
        }
        $issuedFiles = $this->filesReady()
            ? $this->filesModule()->fileManager()->forEntity(
                $documentType,
                $invoice->uid->toString(),
                $invoice->organizationId,
            )
            : [];
        $canViewPayments = $this->paymentsReady()
            && $this->can('kontor-payments-payment-view');
        $allocations = $this->paymentsReady()
            ? $this->paymentModule()->allocationRepository()->forDocument('invoice', $id)
            : [];
        $paymentLabels = [];
        if ($canViewPayments) {
            foreach ($allocations as $allocation) {
                $payment = $this->paymentModule()->paymentRepository()->find($allocation->paymentUid);
                if ($payment === null || !hash_equals($invoice->organizationId, $payment->organizationId)) {
                    continue;
                }
                $paymentLabels[$allocation->paymentUid] = $payment->number
                    ?? $payment->transactionReference
                    ?? sprintf($this->_('Payment on %s'), $allocation->allocatedAt->format('M j, Y'));
            }
        }
        $sourceOrder = null;
        if ($invoice->orderUid !== null && $this->salesReady() && $this->can('kontor-sales-order-view')) {
            $candidate = $this->salesModule()->orderRepository()->find($invoice->orderUid);
            if ($candidate !== null && hash_equals($invoice->organizationId, $candidate->organizationId)) {
                $sourceOrder = $candidate;
            }
        }
        $showLedger = $this->ledgerReady() && $this->can('kontor-ledger-entry-view');

        return $this->renderTemplate('invoice', [
            'invoice' => $invoice,
            'lines' => $module->documentLineRepository()->forDocument(
                $documentType,
                $id,
            ),
            'invoiceTemplate' => $invoiceTemplate,
            'creditNoteTemplate' => $creditNoteTemplate,
            'issuedFile' => $issuedFiles[0] ?? null,
            'customerLabel' => $this->salesCustomerLabels()[
                $invoice->customerType . ':' . $invoice->customerUid
            ] ?? $this->_('Customer unavailable'),
            'sourceOrder' => $sourceOrder,
            'paymentsReady' => $this->paymentsReady(),
            'allocations' => $allocations,
            'paymentLabels' => $paymentLabels,
            'canViewPayments' => $canViewPayments,
            'canRecordPayment' => $this->paymentsReady()
                && $this->can('kontor-payments-payment-create')
                && $this->can('kontor-payments-payment-allocate'),
            'ledgerReady' => $this->ledgerReady(),
            'showLedger' => $showLedger,
            'ledgerPosting' => $showLedger
                ? $this->ledgerModule()->entryRepository()->findByReference(
                    LedgerInvoicePostingService::ISSUE_REFERENCE,
                    $id,
                )
                : null,
            'ledgerCancellation' => $showLedger
                ? $this->ledgerModule()->entryRepository()->findByReference(
                    LedgerInvoicePostingService::CANCELLATION_REFERENCE,
                    $id,
                )
                : null,
            'mailReady' => $this->mailReady(),
            'mailboxes' => $this->mailReady()
                ? array_values(array_filter(
                    $this->mailModule()->mailboxRepository()->forOrganization($invoice->organizationId),
                    static fn (\Kontor\Mail\Domain\Mailbox $mailbox): bool => $mailbox->isActive(),
                ))
                : [],
            'customerEmail' => $this->invoiceCustomerEmail($invoice),
            'canIssue' => $this->can('kontor-invoices-invoice-issue'),
            'canSend' => $this->can('kontor-invoices-invoice-send'),
            'canCancel' => $this->can('kontor-invoices-invoice-cancel'),
            'canCredit' => $this->can('kontor-invoices-credit-note-create'),
            'documentsReady' => $this->documentsReady(),
            'filesReady' => $this->filesReady(),
            'showDocuments' => $this->documentsReady()
                && $this->can('kontor-documents-template-view'),
            'showFiles' => $this->filesReady()
                && $this->can('kontor-files-file-view'),
            'canManageMailboxes' => $this->mailReady()
                && $this->can('kontor-mail-mailbox-manage'),
        ]);
    }

    public function ___executeInvoiceFromOrder(): void
    {
        $this->requirePost();
        $this->requireInvoices();
        $this->requirePermission('kontor-invoices-invoice-create');
        $orderId = $this->wire()->sanitizer->text((string) $this->wire()->input->post('order_uid'));
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');
        $order = $sales->orderRepository()->require($orderId);
        $this->requireSameOrganization($order->organizationId);
        $invoice = $this->invoiceModule()->orderConversionService()->convert($orderId);
        $this->audit(
            'invoices',
            'invoice',
            $invoice->uid->toString(),
            'created',
            current: [
                'orderUid' => $orderId,
                'totalMinor' => $invoice->total->amountMinor(),
                'dueDate' => $invoice->dueDate?->format('Y-m-d'),
            ],
        );
        $this->message($this->_('Invoice draft created from sales order.'));
        $this->wire()->session->redirect(
            '../invoice/?id=' . rawurlencode($invoice->uid->toString())
        );
    }

    public function ___executeInvoiceAction(): void
    {
        $this->requirePost();
        $this->requireInvoices();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['issue', 'send', 'cancel', 'credit', 'archive', 'restore']
        );
        $this->requireAction($action, ['issue', 'send', 'cancel', 'credit', 'archive', 'restore']);
        $permissions = [
            'issue' => 'kontor-invoices-invoice-issue',
            'send' => 'kontor-invoices-invoice-send',
            'cancel' => 'kontor-invoices-invoice-cancel',
            'credit' => 'kontor-invoices-credit-note-create',
            'archive' => 'kontor-invoices-invoice-edit-draft',
            'restore' => 'kontor-invoices-invoice-edit-draft',
        ];
        $this->requirePermission($permissions[$action]);
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $module = $this->invoiceModule();
        $invoice = $module->invoiceRepository()->require($id);
        $this->requireSameOrganization($invoice->organizationId);

        if ($action === 'issue' || $action === 'credit') {
            $this->requireDocuments();
            $this->requireFiles();
            $templateKey = $action === 'credit' ? 'credit_note.standard' : 'invoice.standard';
            $template = $this->documentsModule()->templateRepository()->findCurrentVersion(
                $invoice->organizationId,
                $templateKey,
                $invoice->documentLanguage,
            );
            if ($template === null) {
                throw new WireException(sprintf(
                    $this->_('Publish an active %s document template before issuing this document.'),
                    $templateKey,
                ));
            }

            $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
            $pdo->beginTransaction();
            try {
                $issued = $action === 'credit'
                    ? $module->workflow()->issueCreditNote($id, (int) $this->wire()->user->id)
                    : $module->workflow()->issue($id, (int) $this->wire()->user->id);
                $stored = $this->storeIssuedInvoiceDocument($module, $issued, $template);
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            $this->audit('files', 'file', $stored['uid'], 'generated', metadata: [
                'sourceComponent' => 'invoices',
                'invoiceUid' => $issued->uid->toString(),
                'version' => $stored['versionNumber'],
            ]);

            if ($action === 'credit') {
                $this->audit(
                    'invoices',
                    'invoice',
                    $issued->uid->toString(),
                    'credit_note_issued',
                    current: ['creditedInvoiceUid' => $id, 'number' => $issued->number],
                );
                $this->message($this->_('Credit note issued with an immutable snapshot and private PDF.'));
                $this->wire()->session->redirect(
                    '../invoice/?id=' . rawurlencode($issued->uid->toString())
                );
            }
            $this->message($this->_('Invoice issued with an immutable snapshot and private PDF.'));
        } elseif ($action === 'send') {
            $this->requireMail();
            $mailboxUid = $this->wire()->sanitizer->text(
                (string) $this->wire()->input->post('mailbox_uid')
            );
            $recipient = strtolower(trim((string) $this->wire()->input->post('recipient')));
            $mailbox = $this->mailModule()->mailboxRepository()->require($mailboxUid);
            $this->requireSameOrganization($mailbox->organizationId);
            if (!$mailbox->isActive()) {
                throw new WireException($this->_('Select an active mailbox.'));
            }
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
                throw new WireException($this->_('A valid invoice recipient is required.'));
            }
            $number = $invoice->number ?? $this->_('Invoice');
            $subject = sprintf($this->_('Invoice %s'), $number);
            $body = sprintf(
                $this->_("Hello,\n\nplease find invoice %s for %s %s. Payment is due %s.\n\nRegards"),
                $number,
                number_format($invoice->total->amountMinor() / 100, 2, '.', ''),
                $invoice->total->currencyCode(),
                $invoice->dueDate?->format('Y-m-d') ?? $this->_('on receipt'),
            );
            $dryRun = (bool) $this->wire()->input->post('dry_run');
            $outbound = $dryRun
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
            $message = $outbound->send(
                $invoice->organizationId,
                $mailbox->uid->toString(),
                $mailbox->emailAddress,
                [$recipient],
                [],
                $subject,
                $body,
                (int) $this->wire()->user->id,
            );
            $this->mailModule()->entityLinking()->link(
                $invoice->organizationId,
                $message->uid->toString(),
                'invoice',
                $invoice->uid->toString(),
                (int) $this->wire()->user->id,
            );
            $this->audit('mail', 'message', $message->uid->toString(), 'invoice_delivery', metadata: [
                'invoiceUid' => $invoice->uid->toString(),
                'dryRun' => $dryRun,
                'status' => $message->status,
            ]);
            if ($message->status !== 'sent') {
                throw new WireException(sprintf(
                    $this->_('Invoice delivery failed: %s'),
                    $message->error ?? $this->_('unknown transport error'),
                ));
            }
            $module->workflow()->send($id);
            $this->audit('invoices', 'invoice', $id, 'send', metadata: [
                'mailMessageUid' => $message->uid->toString(),
                'recipient' => $recipient,
                'dryRun' => $dryRun,
            ]);
            $this->message($dryRun
                ? $this->_('Invoice delivery simulated, recorded in Mail, and marked sent.')
                : $this->_('Invoice sent, recorded in Mail, and marked sent.'));
            $this->wire()->session->redirect('../invoice/?id=' . rawurlencode($id));
            return;
        } elseif ($action === 'cancel') {
            $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
            $pdo->beginTransaction();
            try {
                $module->workflow()->cancel($id, (int) $this->wire()->user->id);
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
        } elseif ($action === 'restore') {
            $module->invoiceRepository()->restore($id);
        } else {
            $module->invoiceRepository()->archive($id);
        }

        $this->audit('invoices', 'invoice', $id, $action);
        $this->message($this->_('Invoice updated.'));
        $this->wire()->session->redirect('../invoice/?id=' . rawurlencode($id));
    }

    public function ___executePayments(): string
    {
        $this->requirePayments();
        $this->requirePermission('kontor-payments-payment-view');
        $query = trim($this->wire()->sanitizer->text((string) $this->wire()->input->get('q')));
        $status = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->get('status'),
            ['draft', 'confirmed', 'reversed']
        );
        $module = $this->paymentModule();
        $summaryPayments = $module->paymentRepository()->findMatching(
            $this->organizationUid(),
            limit: 250,
        );
        $payments = $module->paymentRepository()->findMatching(
            $this->organizationUid(),
            $query,
            $status !== '' ? $status : null,
            limit: 50,
        );
        $payerLabels = $this->salesCustomerLabels();
        $paymentContexts = [];
        $payerRoutes = [];
        $canViewInvoices = $this->invoicesReady() && $this->can('kontor-invoices-invoice-view');
        foreach ($payments as $payment) {
            $paymentUid = $payment->uid->toString();
            $activeAllocations = array_values(array_filter(
                $module->allocationRepository()->forPayment($paymentUid),
                static fn ($allocation): bool => !$allocation->isReversed(),
            ));
            $invoiceLabel = null;
            $invoiceRoute = null;
            foreach ($activeAllocations as $allocation) {
                if ($allocation->documentType !== 'invoice' || !$canViewInvoices) {
                    continue;
                }
                $invoice = $this->invoiceModule()->invoiceRepository()->find($allocation->documentUid);
                if ($invoice !== null && hash_equals($invoice->organizationId, $this->organizationUid())) {
                    $invoiceLabel ??= $invoice->number ?? $this->_('Draft invoice');
                    $invoiceRoute ??= 'invoice/?id=' . rawurlencode($allocation->documentUid);
                }
            }
            $paymentContexts[$paymentUid] = [
                'allocationCount' => count($activeAllocations),
                'invoiceLabel' => $invoiceLabel,
                'invoiceRoute' => $invoiceRoute,
            ];

            $payerKey = $payment->payerType . ':' . $payment->payerUid;
            if ($this->contactsReady() && $payment->payerType === 'contact'
                && $this->can('kontor-contacts-contact-view')) {
                $payerRoutes[$payerKey] = 'contact/?id=' . rawurlencode($payment->payerUid);
            } elseif ($this->contactsReady() && $payment->payerType === 'company'
                && $this->can('kontor-contacts-company-view')) {
                $payerRoutes[$payerKey] = 'company/?id=' . rawurlencode($payment->payerUid);
            }
        }
        $this->setPageTitle($this->_('Kontor · Payments'));

        return $this->renderTemplate('payments', [
            'payments' => $payments,
            'summaryPayments' => $summaryPayments,
            'payerLabels' => $payerLabels,
            'payerRoutes' => $payerRoutes,
            'paymentContexts' => $paymentContexts,
            'query' => $query,
            'selectedStatus' => $status ?? '',
            'counts' => [
                'all' => $module->paymentRepository()->countMatching($this->organizationUid()),
                'confirmed' => $module->paymentRepository()->countMatching(
                    $this->organizationUid(),
                    status: 'confirmed',
                ),
                'reversed' => $module->paymentRepository()->countMatching(
                    $this->organizationUid(),
                    status: 'reversed',
                ),
            ],
            'canViewInvoices' => $canViewInvoices,
            'canViewLedger' => $this->ledgerReady() && $this->can('kontor-ledger-entry-view'),
        ]);
    }

    public function ___executePayment(): string
    {
        $this->requirePayments();
        $this->requirePermission('kontor-payments-payment-view');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->get('id'));
        $module = $this->paymentModule();
        $payment = $module->paymentRepository()->require($id);
        $this->requireSameOrganization($payment->organizationId);
        $allocations = $module->allocationRepository()->forPayment($id);
        $invoiceLabels = [];
        $invoiceViews = [];
        $ledgerEntries = [];
        $canViewInvoices = $this->invoicesReady() && $this->can('kontor-invoices-invoice-view');
        $canViewLedger = $this->ledgerReady() && $this->can('kontor-ledger-entry-view');

        foreach ($allocations as $allocation) {
            if ($allocation->documentType !== 'invoice') {
                continue;
            }
            $invoice = $this->invoiceModule()->invoiceRepository()->find($allocation->documentUid);
            if ($invoice !== null && hash_equals($invoice->organizationId, $this->organizationUid())) {
                $invoiceLabels[$allocation->documentUid] = $invoice->number ?? $this->_('Draft invoice');
                $invoiceViews[$allocation->documentUid] = [
                    'label' => $invoice->number ?? $this->_('Draft invoice'),
                    'status' => $invoice->status,
                    'total' => $invoice->total,
                    'due' => $invoice->due,
                    'route' => $canViewInvoices
                        ? 'invoice/?id=' . rawurlencode($allocation->documentUid)
                        : null,
                ];
            }
            if ($canViewLedger) {
                $referenceUid = $allocation->uid->toString();
                $ledgerEntries[$referenceUid] = [
                    'posting' => $this->ledgerModule()->entryRepository()->findByReference(
                        LedgerAllocationPostingService::POSTING_REFERENCE,
                        $referenceUid,
                    ),
                    'reversal' => $this->ledgerModule()->entryRepository()->findByReference(
                        LedgerAllocationPostingService::REVERSAL_REFERENCE,
                        $referenceUid,
                    ),
                ];
            }
        }

        $this->setPageTitle(sprintf(
            $this->_('Kontor · %s'),
            $payment->number ?? $this->_('Draft payment')
        ));

        return $this->renderTemplate('payment', [
            'payment' => $payment,
            'allocations' => $allocations,
            'invoiceLabels' => $invoiceLabels,
            'invoiceViews' => $invoiceViews,
            'ledgerReady' => $canViewLedger,
            'ledgerEntries' => $ledgerEntries,
            'payerLabel' => $this->salesCustomerLabels()[
                $payment->payerType . ':' . $payment->payerUid
            ] ?? $payment->payerUid,
            'payerRoute' => $this->contactsReady()
                && (($payment->payerType === 'contact' && $this->can('kontor-contacts-contact-view'))
                    || ($payment->payerType === 'company' && $this->can('kontor-contacts-company-view')))
                ? $payment->payerType . '/?id=' . rawurlencode($payment->payerUid)
                : null,
            'canReverse' => $this->can('kontor-payments-payment-reverse'),
        ]);
    }

    public function ___executePaymentFromInvoice(): void
    {
        $this->requirePost();
        $this->requirePayments();
        $this->requirePermission('kontor-payments-payment-create');
        $this->requirePermission('kontor-payments-payment-allocate');
        $invoiceId = $this->wire()->sanitizer->text((string) $this->wire()->input->post('invoice_uid'));
        $invoice = $this->invoiceModule()->invoiceRepository()->require($invoiceId);
        $this->requireSameOrganization($invoice->organizationId);

        if (!in_array($invoice->status, ['issued', 'sent', 'overdue', 'partially_paid'], true)) {
            throw new WireException($this->_('This invoice cannot receive a payment in its current status.'));
        }

        $amountText = str_replace(',', '.', trim((string) $this->wire()->input->post('amount')));
        if (!is_numeric($amountText) || (float) $amountText <= 0) {
            throw new WireException($this->_('Payment amount must be greater than zero.'));
        }
        $amount = Money::ofMinor((int) round((float) $amountText * 100), $invoice->currencyCode);
        if ($amount->amountMinor() > $invoice->due->amountMinor()) {
            throw new WireException($this->_('Payment amount cannot exceed the invoice balance.'));
        }

        $method = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('method'),
            ['bank_transfer', 'card', 'cash', 'other']
        ) ?? 'other';
        $reference = trim($this->wire()->sanitizer->text(
            (string) $this->wire()->input->post('transaction_reference')
        ));
        $payment = Payment::create(
            $this->organizationUid(),
            $invoice->customerType,
            $invoice->customerUid,
            $amount,
            method: $method,
            paymentDate: new \DateTimeImmutable('today'),
            transactionReference: $reference !== '' ? $reference : null,
        );
        $module = $this->paymentModule();
        $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
        $pdo->beginTransaction();
        try {
            $module->paymentRepository()->save($payment);
            $payment = $module->workflow()->confirm($payment->uid->toString());
            $allocation = $module->allocationService()->allocate(
                $payment->uid->toString(),
                'invoice',
                $invoiceId,
                $amount,
                (int) $this->wire()->user->id,
            );
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        $this->audit(
            'payments',
            'payment',
            $payment->uid->toString(),
            'captured',
            current: [
                'number' => $payment->number,
                'amountMinor' => $amount->amountMinor(),
                'invoiceUid' => $invoiceId,
                'allocationUid' => $allocation->uid->toString(),
            ],
        );
        $this->message($this->ledgerReady()
            ? $this->_('Payment recorded, allocated, and posted to the ledger.')
            : $this->_('Payment recorded and allocated to the invoice.'));
        $this->wire()->session->redirect(
            '../payment/?id=' . rawurlencode($payment->uid->toString())
        );
    }

    public function ___executePaymentAction(): void
    {
        $this->requirePost();
        $this->requirePayments();
        $action = $this->wire()->sanitizer->option(
            (string) $this->wire()->input->post('action'),
            ['reverse']
        );
        $this->requireAction($action, ['reverse']);
        $this->requirePermission('kontor-payments-payment-reverse');
        $id = $this->wire()->sanitizer->text((string) $this->wire()->input->post('id'));
        $payment = $this->paymentModule()->paymentRepository()->require($id);
        $this->requireSameOrganization($payment->organizationId);
        $pdo = \Kontor\Core\Infrastructure\Database\TranslatingPDO::wrap($this->wire()->database);
        $pdo->beginTransaction();
        try {
            $this->paymentModule()->workflow()->reversePayment(
                $id,
                (int) $this->wire()->user->id,
            );
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
        $this->audit('payments', 'payment', $id, 'reversed');
        $this->message($this->ledgerReady()
            ? $this->_('Payment, allocations, and ledger posting were reversed.')
            : $this->_('Payment and its allocations were reversed.'));
        $this->wire()->session->redirect('../payment/?id=' . rawurlencode($id));
    }
}
