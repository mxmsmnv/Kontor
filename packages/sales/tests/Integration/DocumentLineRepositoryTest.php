<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Money;
use Kontor\Sales\Domain\DocumentLine;
use Kontor\Sales\Infrastructure\Persistence\DocumentLineRepository;

final class DocumentLineRepositoryTest extends DatabaseTestCase
{
    private function repository(): DocumentLineRepository
    {
        return new DocumentLineRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_for_document_round_trips_with_computed_totals(): void
    {
        $repository = $this->repository();
        $line = DocumentLine::create(
            $this->organizationUid, 'quotation', 'quo_01', 'Widget', 2.0, Money::ofMinor(1000, 'EUR'),
            discountType: 'percentage', discountValue: 10.0, taxRate: 20.0,
        );

        $repository->save($line);
        $lines = $repository->forDocument('quotation', 'quo_01');

        $this->assertCount(1, $lines);
        $this->assertSame('Widget', $lines[0]->title);
        $this->assertSame(1800, $lines[0]->subtotal()->amountMinor());
    }

    public function test_for_document_orders_by_sort_order(): void
    {
        $repository = $this->repository();
        $repository->save(DocumentLine::create($this->organizationUid, 'quotation', 'quo_01', 'Second', 1.0, Money::ofMinor(100, 'EUR'), sortOrder: 2));
        $repository->save(DocumentLine::create($this->organizationUid, 'quotation', 'quo_01', 'First', 1.0, Money::ofMinor(100, 'EUR'), sortOrder: 1));

        $lines = $repository->forDocument('quotation', 'quo_01');

        $this->assertSame(['First', 'Second'], array_map(fn ($l) => $l->title, $lines));
    }

    public function test_delete_for_document(): void
    {
        $repository = $this->repository();
        $repository->save(DocumentLine::create($this->organizationUid, 'quotation', 'quo_01', 'Widget', 1.0, Money::ofMinor(100, 'EUR')));

        $repository->deleteForDocument('quotation', 'quo_01');

        $this->assertCount(0, $repository->forDocument('quotation', 'quo_01'));
    }
}
