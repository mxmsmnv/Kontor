<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeImmutable;
use Kontor\API\Application\IdempotencyKeyConflictException;
use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Domain\Quotation;
use Kontor\SDK\ValueObjects\Money;
use PDO;
use ProcessWire\User;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;
use stdClass;

trait CommerceDraftTools
{
    private function commerceDraftTools(): array
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorSales')) {
            return [];
        }
        $uid = $this->uidSchema();
        $key = $this->idempotencySchema();
        $date = ['type' => 'string', 'pattern' => '^\\d{4}-\\d{2}-\\d{2}$'];
        $preview = ['type' => 'boolean', 'description' => 'Check input and totals without saving.'];
        $lines = $this->jsonDocumentSchema(16_384, 'JSON array of 1–20 lines. Each line uses title, unit_price_minor and optional quantity, unit_code, description, and tax_rate.');
        $tools = [];
        if ($modules->isInstalled('KontorContacts')) {
            $tools[] = $this->tool(
                'kontor_quotation_draft_create',
                'Create a quotation draft',
                'Save a new unsent draft for a customer in your organization. Check proposed totals and fields with the user before saving.',
                $this->mcpQuotationDraftCreate(...),
                'draft',
                [
                    'customer_type' => ['type' => 'string', 'enum' => ['contact', 'company']],
                    'customer_uid' => $uid,
                    'currency' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$'],
                    'lines' => $lines, 'idempotency_key' => $key, 'valid_until' => $date,
                    'language' => ['type' => 'string', 'enum' => ['en', 'de', 'fr', 'es']],
                    'preview' => $preview,
                ],
                ['customer_type', 'customer_uid', 'currency', 'lines', 'idempotency_key'],
            );
        }
        $tools[] = $this->tool(
            'kontor_quotation_draft_update',
            'Edit a quotation draft',
            'Set valid-until date, document language or replacement lines on an existing quotation draft. Omitted fields are preserved.',
            $this->mcpQuotationDraftUpdate(...),
            'draft',
            [
                'uid' => $uid,
                'fields' => $this->jsonDocumentSchema(16_384, 'JSON object: valid_until (date or null), language, and lines (replacement array).'),
                'idempotency_key' => $key, 'preview' => $preview,
            ],
            ['uid', 'fields', 'idempotency_key'],
        );
        if ($modules->isInstalled('KontorInvoices')) {
            $tools[] = $this->tool(
                'kontor_invoice_draft_from_order',
                'Create an invoice draft from an order',
                'Copy lines and totals from a confirmed or completed sales order in your organization. Returns the existing invoice UID if the order is already invoiced.',
                $this->mcpInvoiceDraftFromOrder(...),
                'draft',
                ['order_uid' => $uid, 'idempotency_key' => $key, 'due_date' => $date],
                ['order_uid', 'idempotency_key'],
            );
            $tools[] = $this->tool(
                'kontor_invoice_draft_update',
                'Edit an invoice draft header',
                'Set due date or document language on an ordinary invoice draft. Order lines and payment state are preserved.',
                $this->mcpInvoiceDraftUpdate(...),
                'draft',
                [
                    'uid' => $uid,
                    'fields' => $this->jsonDocumentSchema(2_048, 'JSON object: due_date (date or null) and language.'),
                    'idempotency_key' => $key, 'preview' => $preview,
                ],
                ['uid', 'fields', 'idempotency_key'],
            );
        }
        return $tools;
    }

    public function mcpQuotationDraftCreate(
        string $customer_type,
        string $customer_uid,
        string $currency,
        string $lines,
        string $idempotency_key,
        ?string $valid_until = null,
        ?string $language = null,
        bool $preview = false,
    ): array {
        $actor = $this->commerceDraftActor('kontor-sales-quotation-create');
        if (!in_array($customer_type, ['contact', 'company'], true)) {
            throw new WireException('Customer type must be contact or company.');
        }
        $this->commerceDraftActor('kontor-contacts-' . $customer_type . '-view');
        $this->commerceDraftUid($customer_uid);
        if (preg_match('/^[A-Z]{3}$/D', $currency) !== 1) {
            throw new WireException('Use a three-letter uppercase currency.');
        }
        $modules = $this->wire()->modules;
        $this->requireCommerceDraftModule('KontorSales');
        $this->requireCommerceDraftModule('KontorContacts');
        $sales = $modules->get('KontorSales');
        $organization = $this->commerceDraftOrganization();
        $organizationUid = $organization->uid->toString();
        $contacts = $modules->get('KontorContacts');
        $customers = $customer_type === 'contact' ? $contacts->contactRepository() : $contacts->companyRepository();
        if ($customers->find($customer_uid)?->organizationId !== $organizationUid) {
            throw new Wire404Exception('The quotation customer was not found.');
        }
        $date = $this->commerceDraftDate($valid_until);
        $defaultLanguage = strtolower($organization->defaultLanguage);
        $language = $this->commerceDraftLanguage($language ?? (in_array($defaultLanguage, ['en', 'de', 'fr', 'es'], true) ? $defaultLanguage : 'en'));
        $lines = $this->commerceDraftJson($lines, 16_384);

        return $this->commerceDraftWrite($actor, $organizationUid, $idempotency_key, [
            'action' => 'quotation_create', 'customer_type' => $customer_type, 'customer_uid' => $customer_uid,
            'currency' => $currency, 'valid_until' => $valid_until, 'language' => $language, 'lines' => $lines,
        ], $preview, function () use ($actor, $sales, $organizationUid, $customer_type, $customer_uid, $currency, $date, $language, $lines, $preview): array {
            $quotation = Quotation::create($organizationUid, $customer_type, $customer_uid, $currency,
                contactUid: $customer_type === 'contact' ? $customer_uid : null, validUntil: $date, documentLanguage: $language);
            $documentLines = $this->commerceDraftLines($quotation, $lines);
            $quotation->applyTotalsFromLines($documentLines);
            if (!$preview) {
                $sales->quotationRepository()->save($quotation);
                foreach ($documentLines as $line) {
                    $sales->documentLineRepository()->save($line);
                }
                $this->commerceDraftAudit($actor, $quotation, 'created');
            }
            return $this->commerceDraftSummary($quotation, $preview, $preview ? null : $quotation->uid->toString(), count($documentLines));
        });
    }

    public function mcpQuotationDraftUpdate(string $uid, string $fields, string $idempotency_key, bool $preview = false): array
    {
        return $this->updateCommerceDraft('quotation', $uid, $fields, $idempotency_key, $preview);
    }

    public function mcpInvoiceDraftUpdate(string $uid, string $fields, string $idempotency_key, bool $preview = false): array
    {
        return $this->updateCommerceDraft('invoice', $uid, $fields, $idempotency_key, $preview);
    }

    public function mcpInvoiceDraftFromOrder(string $order_uid, string $idempotency_key, ?string $due_date = null): array
    {
        $actor = $this->commerceDraftActor('kontor-invoices-invoice-create');
        $this->commerceDraftActor('kontor-sales-order-view');
        $this->commerceDraftUid($order_uid);
        $this->requireCommerceDraftModule('KontorSales');
        $this->requireCommerceDraftModule('KontorInvoices');
        $modules = $this->wire()->modules;
        $orders = $modules->get('KontorSales')->orderRepository();
        $invoices = $modules->get('KontorInvoices');
        $organization = $this->commerceDraftOrganization()->uid->toString();
        $dueDate = $this->commerceDraftDate($due_date);

        return $this->commerceDraftWrite($actor, $organization, $idempotency_key, [
            'action' => 'invoice_from_order', 'order_uid' => $order_uid, 'due_date' => $due_date,
        ], false, function () use ($actor, $orders, $invoices, $organization, $order_uid, $dueDate): array {
            $order = $orders->find($order_uid, forUpdate: true, includeArchived: false);
            if ($order === null || $order->organizationId !== $organization) {
                throw new Wire404Exception('The sales order was not found.');
            }
            $existing = $invoices->invoiceRepository()->findByOrder($order_uid, forUpdate: true);
            if ($existing !== null) {
                if ($existing->organizationId !== $organization) {
                    throw new WireException('This sales order cannot be converted to an invoice.');
                }
                if (!$this->commerceDraftCan($actor, 'kontor-invoices-invoice-view')) {
                    throw new WireException('This sales order already has an invoice.');
                }
                return ['uid' => $existing->uid->toString(), 'saved' => false, 'already_invoiced' => true];
            }
            if (!in_array($order->orderStatus, ['confirmed', 'completed'], true)) {
                throw new WireException('Confirm or complete this sales order before invoicing it.');
            }
            $invoice = $invoices->orderConversionService()->convert($order_uid, $dueDate);
            $this->commerceDraftAudit($actor, $invoice, 'created');
            return $this->commerceDraftSummary($invoice, false, $invoice->uid->toString());
        });
    }

    private function updateCommerceDraft(string $kind, string $uid, string $fields, string $key, bool $preview): array
    {
        $isQuote = $kind === 'quotation';
        $actor = $this->commerceDraftActor($isQuote ? 'kontor-sales-quotation-edit' : 'kontor-invoices-invoice-edit-draft');
        $this->commerceDraftActor($isQuote ? 'kontor-sales-quotation-view' : 'kontor-invoices-invoice-view');
        $this->commerceDraftUid($uid);
        $moduleName = $isQuote ? 'KontorSales' : 'KontorInvoices';
        $this->requireCommerceDraftModule($moduleName);
        $module = $this->wire()->modules->get($moduleName);
        $repository = $isQuote ? $module->quotationRepository() : $module->invoiceRepository();
        $organization = $this->commerceDraftOrganization()->uid->toString();
        $dateKey = $isQuote ? 'valid_until' : 'due_date';
        $allowed = $isQuote ? [$dateKey, 'language', 'lines'] : [$dateKey, 'language'];
        $input = $this->commerceDraftJson($fields, $isQuote ? 16_384 : 2_048);
        if (!$input instanceof stdClass || (array) $input === [] || array_diff(array_keys((array) $input), $allowed) !== []) {
            throw new WireException('Provide a nonempty JSON object of supported draft fields.');
        }
        $fields = (array) $input;
        ksort($fields);

        return $this->commerceDraftWrite($actor, $organization, $key, ['action' => $kind . '_update', 'uid' => $uid, 'fields' => $fields], $preview,
            function () use ($actor, $module, $repository, $organization, $kind, $dateKey, $uid, $fields, $preview): array {
                $record = $repository->find($uid, forUpdate: !$preview, includeArchived: false);
                if ($record === null || $record->organizationId !== $organization) {
                    throw new Wire404Exception('The document was not found.');
                }
                if (!$record->isDraft() || ($record instanceof Invoice && $record->kind !== 'invoice')) {
                    throw new WireException('Only active quotation or ordinary invoice drafts can be edited.');
                }
                if (array_key_exists('language', $fields)) {
                    $record->documentLanguage = $this->commerceDraftLanguage($fields['language']);
                }
                if (array_key_exists($dateKey, $fields)) {
                    $property = $kind === 'quotation' ? 'validUntil' : 'dueDate';
                    $record->{$property} = $this->commerceDraftDate($fields[$dateKey]);
                }
                $replacement = null;
                if ($record instanceof Quotation && array_key_exists('lines', $fields)) {
                    $replacement = $this->commerceDraftLines($record, $fields['lines']);
                    $record->applyTotalsFromLines($replacement);
                }
                if (!$preview) {
                    if ($replacement !== null) {
                        $lineRepository = $module->documentLineRepository();
                        $lineRepository->deleteForDocument('quotation', $uid, $organization);
                        foreach ($replacement as $line) {
                            $lineRepository->save($line);
                        }
                    }
                    $repository->save($record);
                    $this->commerceDraftAudit($actor, $record, 'updated');
                }
                return $this->commerceDraftSummary($record, $preview, $uid, $replacement === null ? null : count($replacement));
            });
    }

    /** @return list<DocumentLine> */
    private function commerceDraftLines(Quotation $quotation, mixed $input): array
    {
        if (!is_array($input) || !array_is_list($input) || $input === [] || count($input) > 20) {
            throw new WireException('A quotation draft needs between one and twenty lines.');
        }
        $allowed = ['title', 'unit_price_minor', 'quantity', 'unit_code', 'description', 'tax_rate'];
        $lines = [];
        foreach ($input as $position => $raw) {
            if (!$raw instanceof stdClass || array_diff(array_keys((array) $raw), $allowed) !== []) {
                throw new WireException('Quotation line has unsupported fields.');
            }
            $title = $this->commerceDraftText($raw->title ?? null, 255);
            $unit = $this->commerceDraftText($raw->unit_code ?? 'pcs', 20);
            $description = $this->commerceDraftText($raw->description ?? null, 2000, true);
            $price = $raw->unit_price_minor ?? null;
            $quantity = $this->commerceDraftDecimal($raw->quantity ?? 1, 100_000);
            $tax = $this->commerceDraftDecimal($raw->tax_rate ?? 0, 100);
            if ($title === null || $unit === null || !is_int($price) || $price < 0 || $price > 100_000_000_000 || $quantity <= 0) {
                throw new WireException('A quotation line needs title, unit, positive quantity, and a nonnegative bounded minor-unit price.');
            }
            $lines[] = DocumentLine::create(
                $quotation->organizationId, 'quotation', $quotation->uid->toString(), $title, $quantity,
                Money::ofMinor($price, $quotation->currencyCode), description: $description,
                unitCode: $unit, taxRate: $tax, sortOrder: $position + 1,
            );
        }
        return $lines;
    }

    private function commerceDraftDecimal(mixed $value, int $max): float
    {
        if (!is_int($value) && !is_float($value) && !is_string($value)) {
            throw new WireException('Use bounded decimal quantities and tax percentages.');
        }
        if (preg_match('/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,6})?$/D', (string) $value) !== 1 || (float) $value > $max) {
            throw new WireException('Use bounded decimal quantities and tax percentages with at most six decimal places.');
        }
        return (float) $value;
    }

    private function commerceDraftText(mixed $value, int $limit, bool $multiline = false): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || mb_strlen($value) > $limit) {
            throw new WireException('Invalid quotation line text.');
        }
        $sanitizer = $this->wire()->sanitizer;
        $clean = $multiline ? $sanitizer->textarea($value, ['maxLength' => $limit]) : $sanitizer->text($value, ['maxLength' => $limit]);
        return $clean === '' ? null : $clean;
    }

    private function commerceDraftDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new WireException('Use a valid date in YYYY-MM-DD format.');
        }
        return $date;
    }

    private function commerceDraftLanguage(mixed $language): string
    {
        if (!in_array($language, ['en', 'de', 'fr', 'es'], true)) {
            throw new WireException('Choose quotation or invoice language en, de, fr, or es.');
        }
        return $language;
    }

    private function commerceDraftJson(string $document, int $limit): mixed
    {
        try {
            if (strlen($document) <= $limit) {
                return json_decode($document, false, 8, JSON_THROW_ON_ERROR);
            }
        } catch (\JsonException) {
            // Reject malformed input without exposing its contents.
        }
        throw new WireException('Provide a valid bounded JSON document.');
    }

    private function commerceDraftUid(string $uid): void
    {
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/D', $uid) !== 1) {
            throw new WireException('Document and customer UIDs must be valid ULIDs.');
        }
    }

    private function commerceDraftActor(string $permission): User
    {
        $actor = $this->mcpActor();
        if ($actor === null || !$this->commerceDraftCan($actor, $permission)) {
            throw new WirePermissionException('The linked user lacks the required commerce permission.');
        }
        return $actor;
    }

    private function commerceDraftCan(User $actor, string $permission): bool
    {
        return $actor->isSuperuser() || $actor->hasPermission($permission);
    }

    private function requireCommerceDraftModule(string $name): void
    {
        if (!$this->wire()->modules->isInstalled($name)) {
            throw new Wire404Exception('The commerce module is unavailable.');
        }
    }

    private function commerceDraftOrganization(): Organization
    {
        return $this->wire()->modules->get('Kontor')->container()->get(OrganizationRepository::class)
            ->defaultOrganization('US', 'en', 'USD');
    }

    private function commerceDraftSummary(Quotation|Invoice $record, bool $preview, ?string $uid, ?int $count = null): array
    {
        $dateKey = $record instanceof Quotation ? 'valid_until' : 'due_date';
        $date = $record instanceof Quotation ? $record->validUntil : $record->dueDate;
        $result = [
            'uid' => $uid, 'saved' => !$preview, 'status' => $record->status, 'currency' => $record->currencyCode,
            'language' => $record->documentLanguage, $dateKey => $date?->format('Y-m-d'),
            'subtotal_minor' => $record->subtotal->amountMinor(), 'tax_minor' => $record->tax->amountMinor(),
            'total_minor' => $record->total->amountMinor(),
        ];
        return $count === null ? $result : $result + ['line_count' => $count];
    }

    private function commerceDraftWrite(User $actor, string $organization, string $key, array $request, bool $preview, callable $apply): array
    {
        if (preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $key) !== 1) {
            throw new WireException('A valid idempotency key is required.');
        }
        if ($preview) {
            return $apply();
        }
        $modules = $this->wire()->modules;
        $database = $modules->get('Kontor')->container()->get(PDO::class);
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($database);
        try {
            $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
            $response = $idempotency->remember(
                $organization, 'mcp:commerce-draft:' . $actor->id . ':' . hash('sha256', $key), $request,
                fn (): array => ['status' => 200, 'body' => $apply()],
            );
            if ($ownsTransaction) {
                $database->commit();
            }
            return $response->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $database->inTransaction()) {
                $database->rollBack();
            }
            if ($error instanceof IdempotencyKeyConflictException) {
                throw new WireException($error->getMessage());
            }
            if ($error instanceof WireException) {
                throw $error;
            }
            $this->wire()->log->save('kontor-mcp', 'Commerce draft save failed: ' . get_class($error));
            throw new WireException('The draft could not be saved. Retry using the same idempotency key.');
        }
    }

    private function commerceDraftAudit(User $actor, Quotation|Invoice $record, string $action): void
    {
        $container = $this->wire()->modules->get('Kontor')->container();
        $isQuote = $record instanceof Quotation;
        $container->get(AuditLogger::class)->record(
            $container->get(OrganizationRepository::class)->internalIdOf($record->organizationId),
            $isQuote ? 'sales' : 'invoices', $isQuote ? 'quotation' : 'invoice',
            $record->uid->toString(), $action, 'user', (string) $actor->id,
            current: ['status' => 'draft', 'totalMinor' => $record->total->amountMinor()],
            metadata: ['transport' => 'mcp'],
        );
    }
}
