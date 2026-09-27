<?php

declare(strict_types=1);

namespace Kontor\Catalog\Tests\Integration;

use Kontor\Catalog\Domain\CatalogItem;
use Kontor\Catalog\Health\CatalogHealthCheck;
use Kontor\Catalog\Infrastructure\Persistence\CatalogItemRepository;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;

final class CatalogHealthCheckTest extends DatabaseTestCase
{
    public function test_ok_and_reports_counts(): void
    {
        (new CatalogItemRepository($this->pdo, new OrganizationRepository($this->pdo)))
            ->save(CatalogItem::create($this->organizationUid, ['en' => 'Widget']));

        $result = (new CatalogHealthCheck($this->pdo))->run();

        $this->assertSame('ok', $result->status);
        $this->assertSame(1, $result->details['items']);
        $this->assertSame(0, $result->details['categories']);
        $this->assertSame(0, $result->details['priceLists']);
    }
}
