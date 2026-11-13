<?php

declare(strict_types=1);

namespace Kontor\MCP\Application;

use DateTimeImmutable;
use Kontor\API\Application\IdempotencyService;
use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Database\DatabaseConcurrency;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Payments\Domain\Payment;
use Kontor\SDK\ValueObjects\Money;
use PDO;
use ProcessWire\Wire404Exception;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

trait PaymentActionTools
{
    private const MCP_PAYMENT_METHODS = ['bank_transfer', 'card', 'cash', 'other'];

    private function paymentActionTools(): array
    {
        $modules = $this->wire()->modules;
        if (!$modules->isInstalled('KontorPayments') || !$modules->isInstalled('KontorInvoices')) {
            return [];
        }

        return [
            $this->tool(
                'kontor_invoice_payment_record',
                'Record an already received invoice payment',
                'Review the invoice, amount, currency, current balance and method with the user. Records and allocates an existing payment in Kontor. No money is charged.',
                $this->mcpInvoicePaymentRecord(...), 'draft', [
                    'invoice_uid' => $this->uidSchema(),
                    'amount_minor' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 999999999999],
                    'expected_due_minor' => ['type' => 'integer', 'minimum' => 1],
                    'currency' => ['type' => 'string', 'pattern' => '^[A-Z]{3}$'],
                    'method' => ['type' => 'string', 'enum' => self::MCP_PAYMENT_METHODS],
                    'reference' => ['type' => 'string', 'maxLength' => 190, 'description' => 'Optional transfer or receipt reference. Never supply card numbers or credentials.'],
                    'confirmation' => ['type' => 'string', 'const' => 'RECORD_REVIEWED_RECEIVED_PAYMENT'],
                    'idempotency_key' => $this->idempotencySchema(),
                ],
                ['invoice_uid', 'amount_minor', 'expected_due_minor', 'currency', 'method', 'confirmation', 'idempotency_key'],
            ),
        ];
    }

    public function mcpInvoicePaymentRecord(
        string $invoice_uid,
        int $amount_minor,
        int $expected_due_minor,
        string $currency,
        string $method,
        string $confirmation,
        string $idempotency_key,
        string $reference = '',
    ): array {
        $actor = $this->mcpActor();
        $modules = $this->wire()->modules;
        if ($actor === null || !$modules->isInstalled('KontorPayments') || !$modules->isInstalled('KontorInvoices')
            || (!$actor->isSuperuser() && (!$actor->hasPermission('kontor-invoices-invoice-view')
                || !$actor->hasPermission('kontor-payments-payment-create') || !$actor->hasPermission('kontor-payments-payment-allocate')))) {
            throw new WirePermissionException('Invoice view, payment creation and allocation permissions are required.');
        }
        if ($confirmation !== 'RECORD_REVIEWED_RECEIVED_PAYMENT'
            || preg_match('/^[A-Za-z0-9._:-]{8,160}$/D', $idempotency_key) !== 1
            || preg_match('/^[A-Z]{3}$/D', $currency) !== 1 || !in_array($method, self::MCP_PAYMENT_METHODS, true)
            || $amount_minor < 1 || $amount_minor > 999999999999 || $expected_due_minor < $amount_minor) {
            throw new WireException('Provide the reviewed invoice balance, currency, positive amount, method and confirmation.');
        }
        $reference = trim($this->wire()->sanitizer->text($reference, ['maxLength' => 190]));
        $container = $modules->get('Kontor')->container();
        $organizations = $container->get(OrganizationRepository::class);
        $organization = $organizations->defaultOrganization('US', 'en', 'USD')->uid->toString();
        $module = $modules->get('KontorPayments');
        $invoices = $module->invoiceRepository();
        $idempotency = new IdempotencyService($modules->get('KontorAPI')->idempotencyKeyRepository());
        $pdo = $container->get(PDO::class);
        $ownsTransaction = DatabaseConcurrency::beginWriteTransaction($pdo);
        try {
            $invoice = $invoices->find($invoice_uid, forUpdate: true, includeArchived: false);
            if ($invoice === null || !hash_equals($organization, $invoice->organizationId)) {
                throw new Wire404Exception('Invoice not found.');
            }
            $result = $idempotency->remember(
                $organization,
                'mcp:invoice-payment:' . $actor->id . ':' . hash('sha256', $idempotency_key),
                [$invoice_uid, $amount_minor, $expected_due_minor, $currency, $method, $reference],
                function () use ($organization, $organizations, $invoice, $invoice_uid, $amount_minor, $expected_due_minor, $currency, $method, $reference, $module, $actor, $container): array {
                    if ($invoice->kind !== 'invoice' || !in_array($invoice->status, ['issued', 'sent', 'overdue', 'partially_paid'], true)
                        || $invoice->currencyCode !== $currency || $invoice->due->amountMinor() !== $expected_due_minor) {
                        throw new WireException('The invoice balance, currency or status changed. Review the invoice before recording payment.');
                    }
                    $amount = Money::ofMinor($amount_minor, $currency);
                    $payment = Payment::create(
                        $organization,
                        $invoice->customerType,
                        $invoice->customerUid,
                        $amount,
                        method: $method,
                        paymentDate: new DateTimeImmutable('today'),
                        transactionReference: $reference !== '' ? $reference : null,
                    );
                    $payments = $module->paymentRepository();
                    $payments->save($payment);
                    $uid = $payment->uid->toString();
                    $payment = $module->workflow()->confirm($uid);
                    $allocation = $module->allocationService()->allocate($uid, 'invoice', $invoice_uid, $amount, (int) $actor->id);
                    $allocationUid = $allocation->uid->toString();
                    $container->get(AuditLogger::class)->record(
                        $organizations->internalIdOf($organization), 'payments', 'payment', $uid, 'captured',
                        'user', (string) $actor->id, current: [
                            'number' => $payment->number, 'amountMinor' => $amount_minor,
                            'invoiceUid' => $invoice_uid, 'allocationUid' => $allocationUid,
                        ], metadata: ['transport' => 'mcp'],
                    );

                    return ['status' => 201, 'body' => [
                        'payment_uid' => $uid, 'allocation_uid' => $allocationUid, 'invoice_uid' => $invoice_uid,
                        'amount_minor' => $amount_minor, 'currency' => $currency,
                    ]];
                },
            );
            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $result->body;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof WireException) {
                throw $error;
            }

            return $this->safe(static fn () => throw $error);
        }
    }
}
