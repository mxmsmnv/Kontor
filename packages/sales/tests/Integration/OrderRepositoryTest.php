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
}
