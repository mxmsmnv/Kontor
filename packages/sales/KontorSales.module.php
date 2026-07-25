<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Sales\Application\OrderWorkflowService;
use Kontor\Sales\Application\QuotationToOrderConversionService;
use Kontor\Sales\Application\QuotationWorkflowService;
use Kontor\Sales\Health\SalesHealthCheck;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;
use Kontor\Sales\Infrastructure\Persistence\QuotationRepository;
use Kontor\Sales\Migrations\Migration0001CreateQuotationsTable;
use Kontor\Sales\Migrations\Migration0002CreateOrdersTable;
use Kontor\Sales\Migrations\Migration0003CreateDocumentLinesTable;

/**
 * KontorSales bootstrap module (kontor.md Substage 4.1). No import/export/
 * search milestone this substage — Sales only registers its own
 * translations and provides its services directly, it doesn't need to
 * register anything into Core's cross-component registries yet.
 */
class KontorSales extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Sales',
            'summary' => 'Quotations, orders, document lines, quotation-to-order conversion and status workflows.',
            'version' => '001',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorSales',
            'icon' => 'file-text-o',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-sales-quotation-view' => 'View quotations',
                'kontor-sales-quotation-create' => 'Create quotations',
                'kontor-sales-quotation-edit' => 'Edit quotations',
                'kontor-sales-quotation-issue' => 'Issue quotations',
                'kontor-sales-quotation-send' => 'Send quotations',
                'kontor-sales-quotation-accept' => 'Accept quotations',
                'kontor-sales-quotation-cancel' => 'Cancel quotations',
                'kontor-sales-order-view' => 'View orders',
                'kontor-sales-order-create' => 'Create orders',
                'kontor-sales-order-edit' => 'Edit orders',
                'kontor-sales-order-confirm' => 'Confirm orders',
                'kontor-sales-order-complete' => 'Complete orders',
                'kontor-sales-order-cancel' => 'Cancel orders',
            ],
        ];
    }

    private ?QuotationRepository $quotationRepository = null;
    private ?OrderRepository $orderRepository = null;
    private ?DocumentLineRepository $documentLineRepository = null;

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
            $translations->register('KontorSales', $language, $strings);
        }
    }

    public function quotationRepository(): QuotationRepository
    {
        return $this->quotationRepository ??= new QuotationRepository($this->pdo(), $this->organizations());
    }

    public function orderRepository(): OrderRepository
    {
        return $this->orderRepository ??= new OrderRepository($this->pdo(), $this->organizations());
    }

    public function documentLineRepository(): DocumentLineRepository
    {
        return $this->documentLineRepository ??= new DocumentLineRepository($this->pdo(), $this->organizations());
    }

    public function quotationWorkflow(): QuotationWorkflowService
    {
        return new QuotationWorkflowService($this->quotationRepository(), $this->documentLineRepository(), $this->sequences());
    }

    public function orderWorkflow(): OrderWorkflowService
    {
        return new OrderWorkflowService($this->orderRepository());
    }

    public function conversionService(): QuotationToOrderConversionService
    {
        return new QuotationToOrderConversionService(
            $this->quotationRepository(),
            $this->orderRepository(),
            $this->documentLineRepository(),
            $this->sequences(),
        );
    }

    public function healthCheck(): SalesHealthCheck
    {
        return new SalesHealthCheck($this->pdo());
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
            new Migration0001CreateQuotationsTable(),
            new Migration0002CreateOrdersTable(),
            new Migration0003CreateDocumentLinesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('sales', self::getModuleInfo()['version'], 'sales');
        $components->enable('sales');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Sales module removed. Quotation and order data was kept intact.'));
    }
}
