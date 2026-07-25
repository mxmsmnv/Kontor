<?php

declare(strict_types=1);

namespace Kontor\Marketplace\Tests\Unit\Application;

use InvalidArgumentException;
use Kontor\Marketplace\Application\RegistryManagementService;
use Kontor\Marketplace\Infrastructure\Persistence\RegistryRepository;
use PHPUnit\Framework\TestCase;

final class RegistryManagementServiceTest extends TestCase
{
    public function test_official_is_a_reserved_registry_name(): void
    {
        // The guard throws before the repository is ever queried, so a
        // FakePdo (never actually used) is enough here — persisting a
        // real custom registry is covered by
        // tests/Integration/RegistrySyncServiceTest instead.
        $service = new RegistryManagementService(new RegistryRepository(new class extends \PDO {
            public function __construct()
            {
            }
        }));

        $this->expectException(InvalidArgumentException::class);

        $service->registerCustomRegistry('official', 'https://example.com/registry.json');
    }
}
