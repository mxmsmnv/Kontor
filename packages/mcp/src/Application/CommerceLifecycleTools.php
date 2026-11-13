<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use Kontor\API\Application\IdempotencyKeyConflictException;
use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Documents\Domain\DocumentTemplate;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use PDO;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait CommerceLifecycleTools
{
    private const COMMERCE_LIFECYCLE_ACTIONS = [
        'quotation' => [
            'issue' => 'kontor-sales-quotation-issue',
            'accept' => 'kontor-sales-quotation-accept',
            'cancel' => 'kontor-sales-quotation-cancel',
            'convert' => 'kontor-sales-order-create',
        ],
        'invoice' => [
            'issue' => 'kontor-invoices-invoice-issue',
            'cancel' => 'kontor-invoices-invoice-cancel',
        ],
    ];

    private function commerceLifecycleTools(): array
    {
        $tools = [];
        foreach (self::COMMERCE_LIFECYCLE_ACTIONS as $kind => $actions) {
            $module = $kind === 'quotation' ? 'KontorSales' : 'KontorInvoices';
            if (!$this->wire()->modules->isInstalled($module)) {
                continue;
            }
            $args = [
                'uid' => $this->uidSchema(),
                'action' => ['type' => 'string', 'enum' => array_keys($actions)],
            ];
            $tools[] = $this->tool(
                'kontor_' . $kind . '_action_preview',
                'Review ' . $kind . ' action',
                'Review document state, amount and required template before confirming the selected action.',
                $kind === 'quotation' ? $this->mcpQuotationActionPreview(...) : $this->mcpInvoiceActionPreview(...),
                'read',
                $args,
                ['uid', 'action'],
            );
            $tools[] = $this->tool(
                'kontor_' . $kind . '_action_apply',
                'Apply reviewed ' . $kind . ' action',
                'After the user confirms a preview, apply exactly that action using its review token. Issuance creates an immutable private PDF.',
                $kind === 'quotation' ? $this->mcpQuotationActionApply(...) : $this->mcpInvoiceActionApply(...),
                'publish',
                $args + [
                    'review_token' => ['type' => 'string', 'pattern' => '^[a-f0-9]{64}$'],
                    'confirmation' => ['type' => 'string', 'const' => 'APPLY_REVIEWED_COMMERCE_ACTION'],
                    'idempotency_key' => $this->idempotencySchema(),
                ],
                ['uid', 'action', 'review_token', 'confirmation', 'idempotency_key'],
                destructive: true,
            );
        }
        return $tools;
    }

    public function mcpQuotationActionPreview(string $uid, string $action): array
    {
        return $this->previewCommerceLifecycle('quotation', $uid, $action);
    }

    public function mcpInvoiceActionPreview(string $uid, string $action): array
    {
        return $this->previewCommerceLifecycle('invoice', $uid, $action);
    }

    public function mcpQuotationActionApply(string $uid, string $action, string $review_token, string $confirmation, string $idempotency_key): array
    {
        return $this->applyCommerceLifecycle('quotation', $uid, $action, $review_token, $confirmation, $idempotency_key);
    }

    public function mcpInvoiceActionApply(string $uid, string $action, string $review_token, string $confirmation, string $idempotency_key): array
    {
        return $this->applyCommerceLifecycle('invoice', $uid, $action, $review_token, $confirmation, $idempotency_key);
    }

    private function previewCommerceLifecycle(string $kind, string $uid, string $action): array
    {
        [$actor, $module, $repository, $organization] = $this->commerceLifecycleContext($kind, $uid, $action);
        $record = $this->commerceLifecycleRecord($repository, $organization, $uid, false);
        return $this->commerceLifecycleReview($actor, $module, $record, $kind, $action)[0];
    }

    private function applyCommerceLifecycle(string $kind, string $uid, string $action, string $reviewToken, string $confirmation, string $key): array
    {
        if ($confirmation !== 'APPLY_REVIEWED_COMMERCE_ACTION'
            || preg_match('/^[a-f0-9]{64}$/D', $reviewToken) !== 1
            || preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('Provide the reviewed action, its confirmation phrase and a valid idempotency key.');
        }
        [$actor, $module, $repository, $organization, $container] = $this->commerceLifecycleContext($kind, $uid, $action);
        $modules = $this->wire()->modules;
        $database = $container->get(PDO::class);
        $storedPath = null;
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($database);
        try {
            $record = $this->commerceLifecycleRecord($repository, $organization, $uid, true);
            $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
            $response = $idempotency->remember(
                $organization, 'mcp:commerce-action:' . $actor->id . ':' . hash('sha256', $key),
                ['kind' => $kind, 'uid' => $uid, 'action' => $action, 'review_token' => $reviewToken],
                function () use ($actor, $module, $repository, $record, $uid, $organization, $kind, $action, $reviewToken, $container, &$storedPath): array {
                    [$review, $template, $lines, $customerName] = $this->commerceLifecycleReview($actor, $module, $record, $kind, $action);
                    if (!hash_equals($review['review_token'], $reviewToken)) {
                        throw new WireException('The document changed. Review the action again.');
                    }
                    $body = $this->executeCommerceLifecycle($actor, $module, $repository, $record, $kind, $action, $template, $lines, $customerName, $storedPath);
                    $container->get(AuditLogger::class)->record(
                        $container->get(OrganizationRepository::class)->internalIdOf($organization),
                        $kind === 'quotation' ? 'sales' : 'invoices', $kind, $uid,
                        $action, 'user', (string) $actor->id,
                        previous: ['status' => $review['status']], current: $body,
                        metadata: ['transport' => 'mcp'],
                    );
                    return ['status' => 200, 'body' => $body];
                },
            );
            if ($ownsTransaction) {
                $database->commit();
            }
            return $response->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            if ($storedPath !== null) {
                try {
                    $modules->get('KontorFiles')->storage()->delete($storedPath);
                } catch (\Throwable) {
                    $this->wire()->log->save('kontor-mcp', 'Could not remove a private commerce PDF after rollback.');
                }
            }
            if ($error instanceof IdempotencyKeyConflictException) {
                throw new WireException($error->getMessage());
            }
            if ($error instanceof WireException) {
                throw $error;
            }
            $this->wire()->log->save('kontor-mcp', 'Commerce action failed: ' . get_class($error));
            throw new WireException('The commerce action could not be applied. Check the current record and retry with the same idempotency key.');
        }
    }

    private function commerceLifecycleContext(string $kind, string $uid, string $action): array
    {
        $permission = self::COMMERCE_LIFECYCLE_ACTIONS[$kind][$action] ?? null;
        if ($permission === null || preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('Use an available commerce action and a valid document UID.');
        }
        $actor = $this->mcpActor();
        $isQuote = $kind === 'quotation';
        $view = $isQuote ? 'kontor-sales-quotation-view' : 'kontor-invoices-invoice-view';
        if ($actor === null || (!$actor->isSuperuser() && (!$actor->hasPermission($view) || !$actor->hasPermission($permission)))) {
            throw new WirePermissionException('The linked user lacks the required commerce permissions.');
        }
        $modules = $this->wire()->modules;
        $moduleName = $isQuote ? 'KontorSales' : 'KontorInvoices';
        if (!$modules->isInstalled($moduleName)) {
            throw new Wire404Exception('The commerce module is unavailable.');
        }
        $module = $modules->get($moduleName);
        $repository = $isQuote ? $module->quotationRepository() : $module->invoiceRepository();
        $container = $modules->get('Kontor')->container();
        $organization = $container->get(OrganizationRepository::class)->defaultOrganization('US', 'en', 'USD')->uid->toString();

        return [$actor, $module, $repository, $organization, $container];
    }

    private function commerceLifecycleRecord(object $repository, string $organization, string $uid, bool $lock): Quotation|Invoice
    {
        $record = $repository->find($uid, forUpdate: $lock, includeArchived: false);
        if ($record === null || $record->organizationId !== $organization) {
            throw new Wire404Exception('The commerce document was not found.');
        }
        return $record;
    }

    private function commerceLifecycleReview(User $actor, object $module, Quotation|Invoice $record, string $kind, string $action): array
    {
        $available = match ($action) {
            'issue' => $record->isDraft() && (!$record instanceof Invoice || $record->kind === 'invoice'),
            'cancel' => $record instanceof Quotation ? !$record->isClosed() : $record->isCancellable(),
            'accept' => $record->isOpen() && $record->templateUid !== null && $record->snapshot !== [],
            'convert' => $record->isAccepted() && $module->orderRepository()->findByQuotation($record->uid->toString()) === null,
        };
        if (!$available) {
            throw new WireException('The commerce action is unavailable for the current document state.');
        }
        $template = null;
        $lines = [];
        $lineData = [];
        $customerName = null;
        if ($action === 'issue' || $action === 'convert') {
            $lines = $module->documentLineRepository()->forDocument($kind, $record->uid->toString(), $record->organizationId, 51);
            if ($lines === [] || count($lines) > 50) {
                throw new WireException('This action requires a document with one to fifty lines.');
            }
            $record->applyTotalsFromLines($lines);
            $lineData = $this->commerceLifecycleLineData($lines);
        }
        if ($action === 'issue') {
            $modules = $this->wire()->modules;
            if (!$modules->isInstalled('KontorDocuments') || !$modules->isInstalled('KontorFiles')) {
                throw new WireException('Kontor Documents and Files are required to issue a document.');
            }
            $template = $modules->get('KontorDocuments')->templateRepository()->findCurrentVersion(
                $record->organizationId, $kind . '.standard', $record->documentLanguage,
            );
            if ($template === null) {
                throw new WireException('Publish the active document template for this language before issuing.');
            }
            $customerName = $this->commerceLifecycleCustomer($actor, $record);
        }
        $review = [
            'uid' => $record->uid->toString(), 'kind' => $kind, 'action' => $action, 'status' => $record->status,
            'number' => $record->number, 'currency' => $record->currencyCode,
            'total_minor' => $record->total->amountMinor(),
            'language' => $record->documentLanguage,
            'date' => ($record instanceof Quotation ? $record->validUntil : $record->dueDate)?->format('Y-m-d'),
            'template_uid' => $template?->uid->toString(),
            'lines' => array_map(static fn (array $line): array => array_replace($line, [
                'description' => $line['description'] === null ? null : mb_substr($line['description'], 0, 500),
            ]), $lineData),
        ];
        $secret = (string) ($this->wire()->config->tableSalt ?: $this->wire()->config->userAuthSalt);
        if ($secret === '') {
            throw new WireException('The commerce review signing key is unavailable.');
        }
        $review['review_token'] = hash_hmac('sha256', json_encode([
            $actor->id, $record->organizationId, $record->customerType, $record->customerUid, $customerName, $review, $lineData,
        ], JSON_THROW_ON_ERROR), $secret);
        return [$review, $template, $lines, $customerName];
    }

    private function executeCommerceLifecycle(
        User $actor,
        object $module,
        object $repository,
        Quotation|Invoice $record,
        string $kind,
        string $action,
        ?DocumentTemplate $template,
        array $lines,
        ?string $customerName,
        ?string &$storedPath,
    ): array {
        $uid = $record->uid->toString();
        if ($action === 'convert') {
            $order = $module->conversionService()->convert($uid);
            return ['uid' => $uid, 'status' => $record->status, 'order_uid' => $order->uid->toString(), 'order_number' => $order->number];
        }
        if ($record instanceof Quotation) {
            $workflow = $module->quotationWorkflow();
            $updated = match ($action) {
                'issue' => $workflow->issue($uid),
                'accept' => $workflow->accept($uid),
                'cancel' => $workflow->cancel($uid),
            };
        } else {
            $workflow = $module->workflow();
            $updated = $action === 'issue' ? $workflow->issue($uid, (int) $actor->id) : $workflow->cancel($uid, (int) $actor->id);
        }
        $result = ['uid' => $uid, 'status' => $updated->status, 'number' => $updated->number];
        if ($template === null) {
            return $result;
        }

        $modules = $this->wire()->modules;
        $documents = $modules->get('KontorDocuments');
        $data = $this->commerceLifecycleDocumentData($updated, $lines, $kind, $customerName ?? throw new WireException('The reviewed customer is unavailable.'));
        $snapshot = $documents->snapshotBuilder()->build($template, $data);
        $pdf = $documents->renderService()->renderPdf($template, $data);
        $updated->attachIssuedDocument($template->uid->toString(), $snapshot);
        $repository->save($updated);
        $file = $modules->get('KontorFiles')->fileManager()->upload(
            organizationUid: $updated->organizationId, originalName: $updated->number . '.pdf', contents: $pdf,
            visibility: 'private', classification: 'confidential', entityType: $kind, entityUid: $uid,
            metadata: ['source' => $kind === 'quotation' ? 'sales' : 'invoices', 'documentType' => $kind,
                'number' => $updated->number, 'documentSnapshot' => $snapshot],
            actorId: (int) $actor->id,
            onStored: static function (string $path) use (&$storedPath): void { $storedPath = $path; },
        );
        return $result + ['file_uid' => $file['uid']];
    }

    private function commerceLifecycleCustomer(User $actor, Quotation|Invoice $record): string
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorContacts') || !in_array($record->customerType, ['contact', 'company'], true)) {
            throw new Wire404Exception('The document customer type is unavailable.');
        }
        if (!$actor->isSuperuser() && !$actor->hasPermission('kontor-contacts-' . $record->customerType . '-view')) {
            throw new WirePermissionException('The linked user cannot view this document customer type.');
        }
        $contacts = $modules->get('KontorContacts');
        $customer = $record->customerType === 'contact'
            ? $contacts->contactRepository()->find($record->customerUid)
            : $contacts->companyRepository()->find($record->customerUid);
        if ($customer === null || $customer->organizationId !== $record->organizationId) {
            throw new Wire404Exception('The document customer was not found in this organization.');
        }
        return $record->customerType === 'contact' ? $customer->displayName : $customer->legalName;
    }

    private function commerceLifecycleDocumentData(Quotation|Invoice $record, array $lines, string $kind, string $customerName): array
    {
        $data = [
            'title' => $kind === 'quotation' ? 'Quotation' : 'Invoice',
            'number' => $record->number,
            'issueDate' => $record->issueDate?->format('Y-m-d'),
            'customer' => ['type' => $record->customerType, 'uid' => $record->customerUid, 'name' => $customerName],
            'currency' => $record->currencyCode, 'lines' => $this->commerceLifecycleLineData($lines),
            'subtotal' => $this->commerceLifecycleMoney($record->subtotal->amountMinor()),
            'tax' => $this->commerceLifecycleMoney($record->tax->amountMinor()),
            'total' => $this->commerceLifecycleMoney($record->total->amountMinor()),
        ];
        return $record instanceof Quotation
            ? $data + ['validUntil' => $record->validUntil?->format('Y-m-d')]
            : $data + ['kind' => 'invoice', 'dueDate' => $record->dueDate?->format('Y-m-d'), 'creditedInvoiceUid' => null,
                'paid' => $this->commerceLifecycleMoney($record->paid->amountMinor()),
                'due' => $this->commerceLifecycleMoney($record->due->amountMinor())];
    }

    private function commerceLifecycleLineData(array $lines): array
    {
        return array_map(fn (DocumentLine $line): array => [
            'title' => $line->title, 'description' => $line->description, 'quantity' => $line->quantity,
            'unit' => $line->unitCode, 'unitPrice' => $this->commerceLifecycleMoney($line->unitPrice->amountMinor()),
            'taxRate' => $line->taxRate, 'total' => $this->commerceLifecycleMoney($line->total()->amountMinor()),
        ], $lines);
    }

    private function commerceLifecycleMoney(int $minor): string
    {
        $whole = intdiv($minor, 100);
        return ($minor < 0 && $whole === 0 ? '-' : '') . $whole . '.' . str_pad((string) abs($minor % 100), 2, '0', STR_PAD_LEFT);
    }
}
