<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Infrastructure\Persistence;

use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Tests\Integration\DatabaseTestCase;
use Kontor\SDK\ValueObjects\Uid;

final class OrganizationRepositoryTest extends DatabaseTestCase
{
    public function test_default_organization_is_created_once_and_reused(): void
    {
        $repository = new OrganizationRepository($this->pdo);

        $first = $repository->defaultOrganization('US', 'en', 'USD');
        $second = $repository->defaultOrganization('DE', 'de', 'EUR');

        $this->assertTrue($first->uid->equals($second->uid));
        $this->assertSame('US', $second->countryCode);
    }

    public function test_save_then_find_round_trips_all_fields(): void
    {
        $repository = new OrganizationRepository($this->pdo);
        $organization = new Organization(
            uid: Uid::generate(),
            name: 'Acme GmbH',
            legalName: 'Acme Gesellschaft mit beschränkter Haftung',
            countryCode: 'DE',
            defaultLanguage: 'de',
            defaultCurrency: 'EUR',
            timezone: 'Europe/Berlin',
            status: 'active',
            settings: ['fiscalYearStart' => '01-01'],
        );

        $repository->save($organization);
        $found = $repository->require($organization->uid->toString());

        $this->assertSame('Acme GmbH', $found->name);
        $this->assertSame(['fiscalYearStart' => '01-01'], $found->settings);
    }

    public function test_require_throws_for_unknown_uid(): void
    {
        $repository = new OrganizationRepository($this->pdo);

        $this->expectException(\RuntimeException::class);

        $repository->require(Uid::generate()->toString());
    }
}
