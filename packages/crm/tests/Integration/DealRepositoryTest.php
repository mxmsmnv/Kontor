<?php

declare(strict_types=1);

namespace Kontor\CRM\Tests\Integration;

use Kontor\CRM\Domain\Deal;
use Kontor\CRM\Infrastructure\Persistence\DealRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;

final class DealRepositoryTest extends DatabaseTestCase
{
    private function repository(): DealRepository
    {
        return new DealRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips_including_money(): void
    {
        $fixture = $this->createDefaultPipeline();
        $stage = $fixture['stages'][0];

        $repository = $this->repository();
        $deal = Deal::create(
            $this->organizationUid, $fixture['pipeline']->uid->toString(), $stage->uid->toString(),
            'Big deal', value: Money::ofMinor(250000, 'EUR'),
        );
        $repository->save($deal);

        $found = $repository->find($deal->uid->toString());

        $this->assertSame('Big deal', $found->title);
        $this->assertSame(250000, $found->value->amountMinor());
        $this->assertTrue($found->isOpen());
    }

    public function test_for_stage(): void
    {
        $fixture = $this->createDefaultPipeline();
        $stage = $fixture['stages'][0];
        $otherStage = $fixture['stages'][1];

        $repository = $this->repository();
        $repository->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $stage->uid->toString(), 'Deal A'));
        $repository->save(Deal::create($this->organizationUid, $fixture['pipeline']->uid->toString(), $otherStage->uid->toString(), 'Deal B'));

        $this->assertCount(1, $repository->forStage($stage->uid->toString()));
    }

    public function test_matching_search_status_archive_and_count_are_organization_scoped(): void
    {
        $fixture = $this->createDefaultPipeline();
        $stage = $fixture['stages'][0];
        $repository = $this->repository();
        $matching = Deal::create(
            $this->organizationUid,
            $fixture['pipeline']->uid->toString(),
            $stage->uid->toString(),
            'Northwind renewal',
            source: 'Partner',
        );
        $other = Deal::create(
            $this->organizationUid,
            $fixture['pipeline']->uid->toString(),
            $stage->uid->toString(),
            'Different opportunity',
        );
        $repository->save($matching);
        $repository->save($other);

        $this->assertSame(
            [$matching->uid->toString()],
            array_map(
                static fn (Deal $deal): string => $deal->uid->toString(),
                $repository->findMatching(
                    $this->organizationUid,
                    $fixture['pipeline']->uid->toString(),
                    'Northwind',
                    'open',
                )
            )
        );
        $this->assertSame(
            1,
            $repository->countMatching(
                $this->organizationUid,
                $fixture['pipeline']->uid->toString(),
                'Northwind',
                'open',
            )
        );

        $repository->archive($matching->uid->toString());

        $this->assertSame(0, $repository->countMatching(
            $this->organizationUid,
            $fixture['pipeline']->uid->toString(),
            'Northwind',
            'open',
        ));
        $this->assertSame(1, $repository->countMatching(
            $this->organizationUid,
            $fixture['pipeline']->uid->toString(),
            'Northwind',
            'open',
            true,
        ));
        $this->assertSame(
            [$other->uid->toString()],
            array_map(
                static fn (Deal $deal): string => $deal->uid->toString(),
                $repository->forStage($stage->uid->toString())
            )
        );
        $this->assertSame(
            [$matching->uid->toString()],
            array_map(
                static fn (Deal $deal): string => $deal->uid->toString(),
                $repository->forStage($stage->uid->toString(), true)
            )
        );
    }

    public function test_require_throws_for_unknown_uid(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository()->require(\Kontor\SDK\ValueObjects\Uid::generate()->toString());
    }

    public function test_save_rejects_a_non_deal_entity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository()->save(new \stdClass());
    }
}
