<?php

declare(strict_types=1);

namespace Kontor\Sales\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Sales\Domain\Order;
use Kontor\Sales\Infrastructure\Persistence\OrderRepository;

final class OrderRepositoryTest extends DatabaseTestCase
{
    private function repository(): OrderRepository
    {
        return new OrderRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_save_then_find_round_trips(): void
    {
        $repository = $this->repository();
        $order = Order::create($this->organizationUid, 'contact', 'ct_01', 'EUR');

        $repository->save($order);
        $found = $repository->find($order->uid->toString());

        $this->assertSame('pending', $found->orderStatus);
        $this->assertSame('unpaid', $found->paymentStatus);
    }

    public function test_require_throws_for_unknown_uid(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->repository()->require(\Kontor\SDK\ValueObjects\Uid::generate()->toString());
    }

    public function test_save_rejects_a_non_order_entity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->repository()->save(new \stdClass());
    }

    public function test_find_by_quotation_and_matching_filters(): void
    {
        $repository = $this->repository();
        $matching = Order::create(
            $this->organizationUid,
            'contact',
            'ct_northwind',
            'EUR',
            quotationUid: 'qt_northwind',
        );
        $matching->number = 'SO-NORTHWIND';
        $matching->orderStatus = 'confirmed';
        $repository->save($matching);
        $repository->save(Order::create($this->organizationUid, 'contact', 'ct_other', 'EUR'));

        $this->assertSame(
            $matching->uid->toString(),
            $repository->findByQuotation('qt_northwind')?->uid->toString()
        );
        $this->assertSame(
            [$matching->uid->toString()],
            array_map(
                static fn (Order $order): string => $order->uid->toString(),
                $repository->findMatching($this->organizationUid, 'NORTHWIND', 'confirmed')
            )
        );
        $this->assertSame(1, $repository->countMatching($this->organizationUid, 'NORTHWIND', 'confirmed'));

        $repository->archive($matching->uid->toString());

        $this->assertSame(0, $repository->countMatching($this->organizationUid, 'NORTHWIND', 'confirmed'));
        $this->assertSame(1, $repository->countMatching(
            $this->organizationUid,
            'NORTHWIND',
            'confirmed',
            true,
        ));
    }
}
