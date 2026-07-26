<?php

declare(strict_types=1);

namespace Kontor\Mail\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\RelationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Migrations\Migration0006CreateRelationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\Mail\Application\EntityLinkingService;
use Kontor\Mail\Domain\MailMessage;

final class EntityLinkingServiceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0006CreateRelationsTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_relations', 'kontor_organizations', 'kontor_migrations'];
    }

    public function test_link_and_linked_entities(): void
    {
        $message = MailMessage::outbound($this->organizationUid, null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body');
        $relations = new RelationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $service = new EntityLinkingService($relations);

        $service->link($this->organizationUid, $message->uid->toString(), 'contact', 'contact_uid_1');

        $linked = $service->linkedEntities($this->organizationUid, $message->uid->toString());

        $this->assertCount(1, $linked);
        $this->assertSame('contact', $linked[0]['targetType']);
        $this->assertSame('contact_uid_1', $linked[0]['targetUid']);
    }

    public function test_linking_to_multiple_entities(): void
    {
        $message = MailMessage::outbound($this->organizationUid, null, 'a@example.com', ['b@example.com'], [], 'Hi', 'Body');
        $relations = new RelationRepository($this->pdo, new OrganizationRepository($this->pdo));
        $service = new EntityLinkingService($relations);

        $service->link($this->organizationUid, $message->uid->toString(), 'contact', 'contact_uid_1');
        $service->link($this->organizationUid, $message->uid->toString(), 'deal', 'deal_uid_1');

        $linked = $service->linkedEntities($this->organizationUid, $message->uid->toString());

        $this->assertCount(2, $linked);
    }
}
