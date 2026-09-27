<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Testing;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Migrations\Migration0004CreateSequencesTable;
use Kontor\Core\Testing\DatabaseTestCase;

/**
 * A living usage example for the shared DatabaseTestCase (kontor.md
 * Substage 7.4's "testing helpers" milestone) — this is exactly the shape
 * a future component's own test case would take, just with Core's own
 * tables since this test lives in Core itself. It skips cleanly in this
 * sandbox the same way every other integration test here does (no
 * KONTOR_TEST_DB_DSN configured) — see docker-compose.test.yml to run it
 * for real.
 */
final class DatabaseTestCaseTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0004CreateSequencesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_sequences', 'kontor_organizations', 'kontor_migrations'];
    }

    public function test_migrations_ran_and_the_default_organization_was_seeded(): void
    {
        $this->assertNotEmpty($this->organizationUid);

        $sequences = new SequenceService($this->pdo, new OrganizationRepository($this->pdo));
        $this->assertSame('SEQ-00001', $sequences->next($this->organizationUid, 'testing', 'demo', prefix: 'SEQ-', padding: 5));
    }
}
