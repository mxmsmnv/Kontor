<?php

declare(strict_types=1);

namespace Kontor\Core\Tests\Unit\Infrastructure\Registry;

use Kontor\Core\Infrastructure\Registry\ExportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\ImportProviderRegistry;
use Kontor\Core\Infrastructure\Registry\RepositoryRegistry;
use Kontor\SDK\Contracts\ExportProviderInterface;
use Kontor\SDK\Contracts\ImportProviderInterface;
use Kontor\SDK\Contracts\RepositoryInterface;
use Kontor\SDK\DTO\ExportContext;
use Kontor\SDK\DTO\ImportContext;
use Kontor\SDK\DTO\ImportRecordResult;
use Kontor\SDK\DTO\ValidationResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ImportExportRegistryTest extends TestCase
{
    public function test_import_provider_registry_register_and_get(): void
    {
        $registry = new ImportProviderRegistry();
        $provider = new class implements ImportProviderInterface {
            public function entityType(): string { return 'contact'; }
            public function fields(): array { return []; }
            public function validate(array $record, ImportContext $context): ValidationResult { return ValidationResult::valid(); }
            public function findExisting(array $record, ImportContext $context): ?string { return null; }
            public function import(array $record, ImportContext $context): ImportRecordResult { return new ImportRecordResult('created'); }
        };

        $registry->register('contact', $provider);

        $this->assertTrue($registry->has('contact'));
        $this->assertSame($provider, $registry->get('contact'));
    }

    public function test_import_provider_registry_throws_for_unregistered_entity_type(): void
    {
        $this->expectException(RuntimeException::class);

        (new ImportProviderRegistry())->get('contact');
    }

    public function test_export_provider_registry_register_and_get(): void
    {
        $registry = new ExportProviderRegistry();
        $provider = new class implements ExportProviderInterface {
            public function entityType(): string { return 'contact'; }
            public function fields(): array { return ['name']; }
            public function filters(): array { return []; }
            public function count(array $filters, ExportContext $context): int { return 0; }
            public function iterate(array $filters, array $fields, ExportContext $context): iterable { return []; }
        };

        $registry->register('contact', $provider);

        $this->assertTrue($registry->has('contact'));
        $this->assertSame($provider, $registry->get('contact'));
    }

    public function test_export_provider_registry_throws_for_unregistered_entity_type(): void
    {
        $this->expectException(RuntimeException::class);

        (new ExportProviderRegistry())->get('contact');
    }

    public function test_repository_registry_register_and_get(): void
    {
        $registry = new RepositoryRegistry();
        $repository = new class implements RepositoryInterface {
            public function find(string $id): ?object { return null; }
            public function require(string $id): object { return new \stdClass(); }
            public function save(object $entity): void {}
            public function archive(string $id): void {}
            public function restore(string $id): void {}
        };

        $registry->register('contact', $repository);

        $this->assertTrue($registry->has('contact'));
        $this->assertSame($repository, $registry->get('contact'));
    }

    public function test_repository_registry_throws_for_unregistered_entity_type(): void
    {
        $this->expectException(RuntimeException::class);

        (new RepositoryRegistry())->get('contact');
    }
}
