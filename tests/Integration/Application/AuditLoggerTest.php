<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Application;

use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Tests\Integration\DatabaseTestCase;
use Kontor\SDK\ValueObjects\Uid;

final class AuditLoggerTest extends DatabaseTestCase
{
    public function test_record_writes_a_queryable_audit_event(): void
    {
        $logger = new AuditLogger($this->pdo);
        $organizations = new OrganizationRepository($this->pdo);
        $organization = $organizations->defaultOrganization('US', 'en', 'EUR');
        $organizationId = $organizations->internalIdOf($organization->uid->toString());
        $entityUid = Uid::generate()->toString();

        $logger->record(
            organizationId: $organizationId,
            component: 'KontorInvoices',
            entityType: 'invoice',
            entityUid: $entityUid,
            action: 'issue',
            actorType: 'user',
            actorUid: 'usr_01',
            previous: ['status' => 'draft'],
            current: ['status' => 'issued'],
        );

        $statement = $this->pdo->prepare('SELECT * FROM kontor_audit_events WHERE entity_uid = :entity_uid');
        $statement->execute(['entity_uid' => $entityUid]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row);
        $this->assertSame($organizationId, (int) $row['organization_id']);
        $this->assertSame('issue', $row['action']);
        $this->assertSame(['status' => 'draft'], json_decode($row['previous_json'], true, flags: JSON_THROW_ON_ERROR));
        $this->assertSame(['status' => 'issued'], json_decode($row['current_json'], true, flags: JSON_THROW_ON_ERROR));
    }
}
