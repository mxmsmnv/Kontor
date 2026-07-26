<?php

declare(strict_types=1);

namespace Kontor\Payments\Tests\Integration;

use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Migrations\Migration0004CreateSequencesTable;
use Kontor\Invoices\Domain\Invoice;
use Kontor\Invoices\Infrastructure\Persistence\InvoiceRepository;
use Kontor\Invoices\Migrations\Migration0001CreateInvoicesTable;
use Kontor\Ledger\Migrations\Migration0001CreateAccountsTable;
use Kontor\Ledger\Migrations\Migration0002CreateEntriesTable;
use Kontor\Ledger\Migrations\Migration0003CreateLinesTable;
use Kontor\Payments\Migrations\Migration0001CreatePaymentsTable;
use Kontor\Payments\Migrations\Migration0002CreatePaymentAllocationsTable;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;
use Kontor\Sales\Migrations\Migration0003CreateDocumentLinesTable;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected \PDO $pdo;
    protected string $organizationUid;

    protected function setUp(): void
    {
        $dsn = getenv('KONTOR_TEST_DB_DSN');

        if ($dsn === false) {
            $this->markTestSkipped(
                'Set KONTOR_TEST_DB_DSN (and _USER/_PASS) to a MySQL/MariaDB '.
                'instance to run this test. See ../../docker-compose.test.yml.'
            );
        }

        $this->pdo = new \PDO(
            $dsn,
            getenv('KONTOR_TEST_DB_USER') ?: null,
            getenv('KONTOR_TEST_DB_PASS') ?: null,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $this->dropTables();

        $runner = new MigrationRunner($this->pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateOrganizationsTable(),
            new Migration0004CreateSequencesTable(),
            new Migration0003CreateDocumentLinesTable(),
            new Migration0001CreateInvoicesTable(),
            new Migration0001CreateAccountsTable(),
            new Migration0002CreateEntriesTable(),
            new Migration0003CreateLinesTable(),
            new Migration0001CreatePaymentsTable(),
            new Migration0002CreatePaymentAllocationsTable(),
        ]);

        $this->organizationUid = (new OrganizationRepository($this->pdo))
            ->defaultOrganization('US', 'en', 'EUR')
            ->uid
            ->toString();
    }

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->dropTables();
        }
    }

    /**
     * A sent invoice with a single 100.00 EUR (no tax) line — the fixture
     * every allocation test starts from.
     */
    protected function sentInvoice(): Invoice
    {
        $organizations = new OrganizationRepository($this->pdo);
        $invoices = new InvoiceRepository($this->pdo, $organizations);
        $lines = new DocumentLineRepository($this->pdo, $organizations);

        $invoice = Invoice::create($this->organizationUid, 'contact', 'ct_01', 'EUR', dueDate: new \DateTimeImmutable('+30 days'));
        $invoices->save($invoice);

        $lines->save(DocumentLine::create(
            $this->organizationUid, 'invoice', $invoice->uid->toString(), 'Widget', 1.0, Money::ofMinor(10000, 'EUR'),
        ));

        $invoice->applyTotalsFromLines($lines->forDocument('invoice', $invoice->uid->toString()));
        $invoice->number = (new SequenceService($this->pdo, $organizations))->next($this->organizationUid, 'invoices', 'invoice', prefix: 'INV-');
        $invoice->status = 'sent';
        $invoice->issuedAt = new \DateTimeImmutable();
        $invoice->sentAt = new \DateTimeImmutable();
        $invoices->save($invoice);

        return $invoices->require($invoice->uid->toString());
    }

    private function dropTables(): void
    {
        foreach (
            [
                'kontor_payment_allocations',
                'kontor_payments',
                'kontor_ledger_lines',
                'kontor_ledger_entries',
                'kontor_ledger_accounts',
                'kontor_invoices',
                'kontor_document_lines',
                'kontor_sequences',
                'kontor_organizations',
                'kontor_migrations',
            ] as $table
        ) {
            $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
        }
    }
}
