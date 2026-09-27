<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Payments\Application\PaymentAllocationService;
use Kontor\Payments\Application\PaymentWorkflowService;
use Kontor\Payments\Application\LedgerAllocationPostingService;
use Kontor\Payments\Health\PaymentsHealthCheck;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use Kontor\Payments\Migrations\Migration0001CreatePaymentsTable;
use Kontor\Payments\Migrations\Migration0002CreatePaymentAllocationsTable;

/**
 * KontorPayments bootstrap module (kontor.md Substage 4.4). Requires
 * KontorInvoices and KontorSales — it drives Invoice.paid/due/status and
 * carries that settlement state back to the originating Sales order.
 */
class KontorPayments extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Payments',
            'summary' => 'Payments, allocations, partial payments, reversals.',
            'version' => '006',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorPayments',
            'icon' => 'money',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorInvoices', 'KontorSales'],
            'permissions' => [
                'kontor-payments-payment-view' => 'View payments',
                'kontor-payments-payment-create' => 'Create payments',
                'kontor-payments-payment-edit-draft' => 'Edit draft payments',
                'kontor-payments-payment-allocate' => 'Allocate payments to documents',
                'kontor-payments-payment-reverse' => 'Reverse payments and allocations',
                'kontor-payments-refund-create' => 'Create refunds',
            ],
        ];
    }

    private ?PaymentRepository $paymentRepository = null;
    private ?PaymentAllocationRepository $allocationRepository = null;
    private ?InvoiceRepository $invoiceRepository = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

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
            $translations->register('KontorPayments', $language, $strings);
        }
    }

    public function paymentRepository(): PaymentRepository
    {
        return $this->paymentRepository ??= new PaymentRepository($this->pdo(), $this->organizations());
    }

    public function allocationRepository(): PaymentAllocationRepository
    {
        return $this->allocationRepository ??= new PaymentAllocationRepository($this->pdo(), $this->organizations());
    }

    public function invoiceRepository(): InvoiceRepository
    {
        return $this->invoiceRepository ??= new InvoiceRepository($this->pdo(), $this->organizations());
    }

    public function allocationService(): PaymentAllocationService
    {
        $posting = null;
        if ($this->wire()->modules->isInstalled('KontorLedger')) {
            /** @var KontorLedger $ledger */
            $ledger = $this->wire()->modules->get('KontorLedger');
            $posting = new LedgerAllocationPostingService(
                $ledger->accountRepository(),
                $ledger->entryRepository(),
                $ledger->entries(),
            );
        }

        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');

        return new PaymentAllocationService(
            $this->paymentRepository(),
            $this->allocationRepository(),
            $this->invoiceRepository(),
            $posting,
            $sales->orderRepository(),
        );
    }

    public function workflow(): PaymentWorkflowService
    {
        return new PaymentWorkflowService($this->paymentRepository(), $this->allocationRepository(), $this->sequences(), $this->allocationService());
    }

    public function healthCheck(): PaymentsHealthCheck
    {
        return new PaymentsHealthCheck($this->pdo());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function sequences(): SequenceService
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(SequenceService::class);
    }

    private function pdo(): \PDO
    {
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreatePaymentsTable(),
            new Migration0002CreatePaymentAllocationsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('payments', self::getModuleInfo()['version'], 'payments');
        $components->enable('payments');
    }

    public function ___upgrade(int $fromVersion, int $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('payments', self::getModuleInfo()['version'], 'payments');
        $components->enable('payments');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Payments module removed. Payment data was kept intact.'));
    }
}
