<?php

namespace ProcessWire;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\Files\Infrastructure\Storage\SignedUrlSigner;
use Kontor\Payments\Infrastructure\Persistence\PaymentAllocationRepository;
use Kontor\Payments\Infrastructure\Persistence\PaymentRepository;
use Kontor\Portal\Application\CustomerFileService;
use Kontor\Portal\Application\CustomerPaymentService;
use Kontor\Portal\Application\CustomerProfileService;
use Kontor\Portal\Application\PortalAuthenticationService;
use Kontor\Portal\Application\PortalFileDownloadHandler;
use Kontor\Portal\Health\PortalHealthCheck;
use Kontor\Portal\Infrastructure\Persistence\CustomerInvoiceRepository;
use Kontor\Portal\Infrastructure\Persistence\CustomerQuotationRepository;
use Kontor\Portal\Infrastructure\Persistence\PortalAccountRepository;
use Kontor\Portal\Migrations\Migration0001CreatePortalAccountsTable;

/**
 * KontorPortal bootstrap module (kontor.md Substage 9.2, second component
 * of Stage 9). Depends on kontor/core plus kontor/contacts, kontor/sales,
 * kontor/invoices, kontor/payments and kontor/files — an aggregator over
 * five sibling packages' own repositories/domain classes, reused
 * directly rather than duplicated, the same "genuine cross-package
 * dependency" precedent kontor/projects/kontor/purchasing already
 * established for their own "integration" milestones. None of those five
 * packages is ever modified by this one.
 */
class KontorPortal extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor Portal',
            'summary' => 'Customer login, quotations, invoices, payments, files, profile.',
            'version' => '003',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorPortal',
            'icon' => 'user-circle',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorContacts', 'KontorSales', 'KontorInvoices', 'KontorPayments', 'KontorFiles'],
            'permissions' => [
                'kontor-portal-account-manage' => 'Create and manage customer portal accounts',
            ],
        ];
    }

    private ?PortalAccountRepository $accountRepository = null;
    private ?CustomerQuotationRepository $quotationRepository = null;
    private ?CustomerInvoiceRepository $invoiceRepository = null;
    private ?SignedUrlSigner $signer = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));

        $this->addHookBefore('ProcessPageView::execute', $this, 'hookFileDownload');
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__."/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorPortal', $language, $strings);
        }
    }

    /**
     * The real customer-facing file download endpoint. Only this thin
     * translation layer touches superglobals/`$this->wire()` —
     * `PortalFileDownloadHandler` is plain-scalar and fully unit-tested.
     * Lint-checked only in this sandbox, the same disclosed limitation as
     * every other ProcessWire-specific hook glue in this monorepo.
     */
    public function hookFileDownload(HookEvent $event): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = trim((string) (parse_url($requestUri, PHP_URL_PATH) ?? ''), '/');

        if ($path !== 'portal/files/download') {
            return;
        }

        parse_str((string) (parse_url($requestUri, PHP_URL_QUERY) ?? ''), $query);

        $result = $this->fileDownloadHandler()->handle(
            (string) ($query['path'] ?? ''),
            (int) ($query['expires'] ?? 0),
            (string) ($query['signature'] ?? ''),
        );

        http_response_code($result->status);

        if ($result->status === 200 && $result->stream !== null) {
            header("Content-Type: {$result->mimeType}");
            fpassthru($result->stream);
            fclose($result->stream);
        }

        $event->replace = true;
        $event->return = '';
    }

    public function accountRepository(): PortalAccountRepository
    {
        return $this->accountRepository ??= new PortalAccountRepository($this->pdo(), $this->organizations());
    }

    public function quotationRepository(): CustomerQuotationRepository
    {
        return $this->quotationRepository ??= new CustomerQuotationRepository($this->pdo(), $this->organizations());
    }

    public function invoiceRepository(): CustomerInvoiceRepository
    {
        return $this->invoiceRepository ??= new CustomerInvoiceRepository($this->pdo(), $this->organizations());
    }

    public function authentication(): PortalAuthenticationService
    {
        return new PortalAuthenticationService($this->accountRepository());
    }

    public function payments(): CustomerPaymentService
    {
        return new CustomerPaymentService(
            new PaymentAllocationRepository($this->pdo(), $this->organizations()),
            new PaymentRepository($this->pdo(), $this->organizations()),
        );
    }

    public function files(): CustomerFileService
    {
        /** @var KontorFiles $filesModule */
        $filesModule = $this->wire()->modules->get('KontorFiles');

        return new CustomerFileService($filesModule->fileRepository(), $this->signer());
    }

    public function fileDownloadHandler(): PortalFileDownloadHandler
    {
        /** @var KontorFiles $filesModule */
        $filesModule = $this->wire()->modules->get('KontorFiles');

        return new PortalFileDownloadHandler($this->signer(), $filesModule->storage());
    }

    public function profile(): CustomerProfileService
    {
        /** @var KontorContacts $contactsModule */
        $contactsModule = $this->wire()->modules->get('KontorContacts');

        return new CustomerProfileService($contactsModule->contactRepository());
    }

    public function healthCheck(): PortalHealthCheck
    {
        /** @var KontorContacts $contactsModule */
        $contactsModule = $this->wire()->modules->get('KontorContacts');

        return new PortalHealthCheck($this->accountRepository(), $contactsModule->contactRepository());
    }

    /**
     * Portal's own signer instance — same secret as `kontor/files`'s own
     * (ProcessWire's `config->authSalt`), but pointed at Portal's real
     * download endpoint rather than `kontor/files`'s still-placeholder
     * one. See `CustomerFileService`'s own doc comment for why.
     */
    private function signer(): SignedUrlSigner
    {
        return $this->signer ??= new SignedUrlSigner(
            secret: $this->wire()->config->authSalt,
            baseUrl: $this->wire()->config->urls->root.'portal/files/download',
        );
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
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
            new Migration0001CreatePortalAccountsTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('portal', self::getModuleInfo()['version'], 'portal');
        $components->enable('portal');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('portal', self::getModuleInfo()['version'], 'portal');
        $components->enable('portal');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor Portal module removed. Portal accounts were kept intact.'));
    }
}
