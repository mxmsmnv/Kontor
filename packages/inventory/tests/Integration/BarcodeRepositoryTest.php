<?php

declare(strict_types=1);

namespace Kontor\Inventory\Tests\Integration;

use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Inventory\Infrastructure\Persistence\BarcodeRepository;

final class BarcodeRepositoryTest extends DatabaseTestCase
{
    private function repository(): BarcodeRepository
    {
        return new BarcodeRepository($this->pdo, new OrganizationRepository($this->pdo));
    }

    public function test_register_then_resolve(): void
    {
        $repository = $this->repository();
        $repository->register($this->organizationUid, '0123456789012', 'itm_widget');

        $this->assertSame('itm_widget', $repository->resolve($this->organizationUid, '0123456789012'));
    }

    public function test_resolve_returns_null_for_an_unknown_barcode(): void
    {
        $this->assertNull($this->repository()->resolve($this->organizationUid, 'does-not-exist'));
    }

    public function test_registering_the_same_barcode_again_updates_the_item(): void
    {
        $repository = $this->repository();
        $repository->register($this->organizationUid, '0123456789012', 'itm_old');
        $repository->register($this->organizationUid, '0123456789012', 'itm_new');

        $this->assertSame('itm_new', $repository->resolve($this->organizationUid, '0123456789012'));
    }
}
