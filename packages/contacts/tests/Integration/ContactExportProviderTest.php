<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

use Kontor\Contacts\Domain\Contact;
use Kontor\Contacts\Infrastructure\Export\ContactExportProvider;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\DTO\ExportContext;

final class ContactExportProviderTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $repository = new ContactRepository($this->pdo, new OrganizationRepository($this->pdo));
        $repository->save(Contact::create($this->organizationUid, 'Ada', null, 'Lovelace', status: 'active'));
        $repository->save(Contact::create($this->organizationUid, 'Grace', null, 'Hopper', status: 'inactive'));
    }

    private function provider(): ContactExportProvider
    {
        return new ContactExportProvider($this->pdo, new OrganizationRepository($this->pdo));
    }

    private function context(): ExportContext
    {
        return new ExportContext($this->organizationUid, 'user', 'usr_01');
    }

    public function test_count_and_iterate_return_all_active_contacts(): void
    {
        $provider = $this->provider();

        $this->assertSame(2, $provider->count([], $this->context()));

        $rows = iterator_to_array($provider->iterate([], [], $this->context()));
        $this->assertCount(2, $rows);
    }

    public function test_filters_by_status(): void
    {
        $provider = $this->provider();
        $filters = ['status' => 'inactive'];

        $this->assertSame(1, $provider->count($filters, $this->context()));

        $rows = iterator_to_array($provider->iterate($filters, [], $this->context()));
        $this->assertSame('Grace Hopper', $rows[0]['display_name']);
    }

    public function test_sparse_fields_restrict_returned_columns(): void
    {
        $rows = iterator_to_array($this->provider()->iterate([], ['uid', 'display_name'], $this->context()));

        $this->assertSame(['uid', 'display_name'], array_keys($rows[0]));
    }

    public function test_unknown_requested_fields_are_ignored_rather_than_causing_a_sql_error(): void
    {
        $rows = iterator_to_array($this->provider()->iterate([], ['uid', 'DROP TABLE kontor_contacts; --'], $this->context()));

        $this->assertSame(['uid'], array_keys($rows[0]));

        $stillExists = (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_contacts')->fetchColumn();
        $this->assertSame(2, $stillExists);
    }
}
