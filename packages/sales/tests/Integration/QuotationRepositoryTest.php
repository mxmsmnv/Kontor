<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Sales\Domain\Quotation;
use Kontor\Sales\Infrastructure\Persistence\QuotationRepository;

final class QuotationRepositoryTest extends DatabaseTestCase
{
    private function repository(): QuotationRepository
    {
        return new QuotationRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');

        $repository->save($quotation);
        $found = $repository->find($quotation->uid->toString());

        $this->assertSame('ct_01', $found->customerUid);
        $this->assertSame('draft', $found->status);
        $this->assertNull($found->number);
    }

    public function test_require_throws_for_unknown_uid(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository()->require(\Kontor\SDK\ValueObjects\Uid::generate()->toString());
    }

    public function test_save_upserts_rather_than_duplicating(): void
    {
        $repository = $this->repository();
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $repository->save($quotation);

        $quotation->status = 'issued';
        $repository->save($quotation);

        $this->assertSame('issued', $repository->find($quotation->uid->toString())->status);
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM kontor_sales_quotations')->fetchColumn());
    }

    public function test_issued_template_and_snapshot_round_trip(): void
    {
        $repository = $this->repository();
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $quotation->status = 'issued';
        $quotation->attachIssuedDocument(
            '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ['templateVersion' => 3, 'html' => '<main>Immutable</main>'],
        );
        $repository->save($quotation);

        $found = $repository->require($quotation->uid->toString());

        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $found->templateUid);
        $this->assertEquals(
            ['templateVersion' => 3, 'html' => '<main>Immutable</main>'],
            $found->snapshot,
        );
    }

    public function test_archive_then_restore(): void
    {
        $repository = $this->repository();
        $quotation = Quotation::create($this->organizationUid, 'contact', 'ct_01', 'EUR');
        $repository->save($quotation);

        $repository->archive($quotation->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_sales_quotations')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotNull($row['archived_at']);

        $repository->restore($quotation->uid->toString());
        $row = $this->pdo->query('SELECT archived_at FROM kontor_sales_quotations')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNull($row['archived_at']);
    }

    public function test_matching_search_status_archive_and_count(): void
    {
        $repository = $this->repository();
        $matching = Quotation::create($this->organizationUid, 'contact', 'ct_northwind', 'EUR');
        $matching->number = 'QUO-NORTHWIND';
        $matching->status = 'issued';
        $repository->save($matching);
        $repository->save(Quotation::create($this->organizationUid, 'contact', 'ct_other', 'EUR'));

        $this->assertSame(
            [$matching->uid->toString()],
            array_map(
                static fn (Quotation $quotation): string => $quotation->uid->toString(),
                $repository->findMatching($this->organizationUid, 'NORTHWIND', 'issued')
            )
        );
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'NORTHWIND', 'issued'));

        $repository->archive($matching->uid->toString());

        $this->assertSame(0, $repository->countMatching($this->organizationUid, 'NORTHWIND', 'issued'));
        $this->assertSame(1, $repository->countMatching(
            $this->organizationUid,
            'NORTHWIND',
            'issued',
            true,
        ));
    }
}
