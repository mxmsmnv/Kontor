<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Application;

use Kontor\Core\Application\AuditLogger;
use Kontor\Core\Tests\Integration\DatabaseTestCase;
use Kontor\SDK\ValueObjects\Uid;

final class AuditLoggerTest extends DatabaseTestCase
{
    public function test_record_writes_a_queryable_audit_event(): void
    {
        $logger = new AuditLogger($this->pdo);
        $orgUid = Uid::generate()->toString();
        $entityUid = Uid::generate()->toString();

        $logger->record(
            organizationId: $orgUid,
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
        $this->assertSame('issue', $row['action']);
        $this->assertSame('{"status":"draft"}', $row['previous_json']);
        $this->assertSame('{"status":"issued"}', $row['current_json']);
    }
}
