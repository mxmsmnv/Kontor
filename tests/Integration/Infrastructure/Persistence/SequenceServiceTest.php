<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Integration\Infrastructure\Persistence;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Persistence\SequenceService;
use Kontor\Core\Tests\Integration\DatabaseTestCase;

final class SequenceServiceTest extends DatabaseTestCase
{
    private string $organizationUid;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organizationUid = (new OrganizationRepository($this->pdo))
            ->defaultOrganization('US', 'en', 'EUR')
            ->uid
            ->toString();
    }

    private function service(): SequenceService
    {
        return new SequenceService($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_first_call_starts_at_one(): void
    {
        $number = $this->service()->next($this->organizationUid, 'sales', 'quotation', prefix: 'QUO-', padding: 4);

        $this->assertSame('QUO-0001', $number);
    }

    public function test_subsequent_calls_increment(): void
    {
        $service = $this->service();
        $service->next($this->organizationUid, 'sales', 'quotation', prefix: 'QUO-', padding: 4);
        $second = $service->next($this->organizationUid, 'sales', 'quotation');

        $this->assertSame('QUO-0002', $second);
    }

    public function test_later_calls_ignore_new_prefix_and_keep_the_original_configuration(): void
    {
        $service = $this->service();
        $service->next($this->organizationUid, 'sales', 'quotation', prefix: 'QUO-', padding: 4);

        $second = $service->next($this->organizationUid, 'sales', 'quotation', prefix: 'DIFFERENT-', padding: 2);

        $this->assertSame('QUO-0002', $second);
    }

    public function test_different_sequence_keys_are_independent(): void
    {
        $service = $this->service();

        $quotation = $service->next($this->organizationUid, 'sales', 'quotation', prefix: 'QUO-', padding: 4);
        $order = $service->next($this->organizationUid, 'sales', 'order', prefix: 'SO-', padding: 4);

        $this->assertSame('QUO-0001', $quotation);
        $this->assertSame('SO-0001', $order);
    }

    public function test_different_organizations_are_independent(): void
    {
        $secondOrg = \Kontor\Core\Domain\Organization::createDefault('DE', 'de', 'EUR');
        (new OrganizationRepository($this->pdo))->save($secondOrg);

        $service = $this->service();
        $first = $service->next($this->organizationUid, 'sales', 'quotation', prefix: 'QUO-', padding: 4);
        $second = $service->next($secondOrg->uid->toString(), 'sales', 'quotation', prefix: 'QUO-', padding: 4);

        $this->assertSame('QUO-0001', $first);
        $this->assertSame('QUO-0001', $second);
    }

    public function test_yearly_reset_policy_resets_the_counter_when_the_marker_changes(): void
    {
        $service = $this->service();
        $service->next($this->organizationUid, 'sales', 'quotation', prefix: 'QUO-', padding: 4, resetPolicy: 'yearly');

        // simulate the stored marker being from a previous year
        $this->pdo->exec("UPDATE kontor_sequences SET reset_marker = '2020' WHERE sequence_key = 'quotation'");

        $afterReset = $service->next($this->organizationUid, 'sales', 'quotation');

        $this->assertSame('QUO-0001', $afterReset);
    }

    public function test_no_reset_when_policy_is_never(): void
    {
        $service = $this->service();
        $service->next($this->organizationUid, 'sales', 'quotation', prefix: 'QUO-', padding: 4, resetPolicy: 'never');
        $service->next($this->organizationUid, 'sales', 'quotation');
        $third = $service->next($this->organizationUid, 'sales', 'quotation');

        $this->assertSame('QUO-0003', $third);
    }
}
