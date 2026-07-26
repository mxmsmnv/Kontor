<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Invoices\Application\InvoiceWorkflowService;
use Kontor\Invoices\Application\OrderToInvoiceConversionService;
use Kontor\Invoices\Health\InvoicesHealthCheck;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Invoices\Migrations\Migration0001CreateInvoicesTable;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;

/**
 * KontorInvoices bootstrap module (kontor.md Substage 4.3). Requires
 * KontorSales (not just kontor/sales the Composer package) because it
 * reuses Sales' kontor_document_lines table and DocumentLine/
 * DocumentLineRepository classes directly rather than duplicating them —
 * see this package's README.
 */
class KontorInvoices extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Invoices',
            'summary' => 'Invoices, issue workflow, numbering, overdue state, credit notes.',
            'version' => '004',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorInvoices',
            'icon' => 'file-text',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorSales'],
            'permissions' => [
                'kontor-invoices-invoice-view' => 'View invoices',
                'kontor-invoices-invoice-create' => 'Create invoices',
                'kontor-invoices-invoice-edit-draft' => 'Edit draft invoices',
                'kontor-invoices-invoice-issue' => 'Issue invoices',
                'kontor-invoices-invoice-send' => 'Send invoices',
                'kontor-invoices-invoice-cancel' => 'Cancel invoices',
                'kontor-invoices-credit-note-create' => 'Create credit notes',
                'kontor-invoices-credit-note-issue' => 'Issue credit notes',
                'kontor-invoices-numbering-admin' => 'Administer invoice numbering',
            ],
        ];
    }

    private ?InvoiceRepository $invoiceRepository = null;
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
            $translations->register('KontorInvoices', $language, $strings);
        }
    }

    public function invoiceRepository(): InvoiceRepository
    {
        return $this->invoiceRepository ??= new InvoiceRepository($this->pdo(), $this->organizations());
    }

    public function documentLineRepository(): DocumentLineRepository
    {
        return $this->documentLineRepository ??= new DocumentLineRepository($this->pdo(), $this->organizations());
    }

    public function workflow(): InvoiceWorkflowService
    {
        return new InvoiceWorkflowService($this->invoiceRepository(), $this->documentLineRepository(), $this->sequences());
    }

    public function orderConversionService(): OrderToInvoiceConversionService
    {
        /** @var KontorSales $sales */
        $sales = $this->wire()->modules->get('KontorSales');

        return new OrderToInvoiceConversionService(
            $this->invoiceRepository(),
            $sales->orderRepository(),
            $this->documentLineRepository(),
        );
    }

    public function healthCheck(): InvoicesHealthCheck
    {
        return new InvoicesHealthCheck($this->pdo());
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
            new Migration0001CreateInvoicesTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('invoices', self::getModuleInfo()['version'], 'invoices');
        $components->enable('invoices');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('invoices', self::getModuleInfo()['version'], 'invoices');
        $components->enable('invoices');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Invoices module removed. Invoice data was kept intact.'));
    }
}
