<?php

declare(strict_types=1);

namespace Kontor\MCP\Tests\Unit;

use Kontor\API\Application\IdempotencyService;
use Kontor\API\Contracts\ApiResourceInterface;
use Kontor\API\Contracts\IdempotencyStoreInterface;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\DTO\ApiResourceSchema;
use Kontor\API\DTO\StoredIdempotentResponse;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\Core\Infrastructure\Registry\ComponentRegistryInterface;
use Kontor\MCP\Application\KontorMcpBridge;
use PHPUnit\Framework\TestCase;

final class KontorMcpBridgeTest extends TestCase
{
    public const UID = '01KYG385K3FX93BZPYTM5GEZQQ';

    public function testDiscoversLiveResourcesAndBoundsLists(): void
    {
        [$bridge] = $this->bridge();

        $schemas = $bridge->resourceSchemas();
        $list = $bridge->listRecords('contacts', 1, 500, '{"status":"active"}', '[]', '["uid","name"]');

        self::assertSame('contacts', $schemas['items'][0]['resource']);
        self::assertSame(100, $list['page_size']);
        self::assertSame([['uid' => self::UID, 'name' => 'Ada']], $list['items']);
    }

    public function testCreateIsIdempotentAndSecretsAreRejected(): void
    {
        [$bridge, $resource] = $this->bridge();

        $first = $bridge->createRecord('contacts', '{"name":"Grace"}', 'create-0001');
        $second = $bridge->createRecord('contacts', '{"name":"Grace"}', 'create-0001');

        self::assertSame($first, $second);
        self::assertSame(1, $resource->createCalls);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Sensitive field');
        $bridge->validateRecord('contacts', 'create', '{"name":"Grace","apiToken":"unsafe"}');
    }

    public function testArchiveRequiresConfirmationAndIsReplaySafe(): void
    {
        [$bridge, $resource] = $this->bridge();

        try {
            $bridge->archiveRecord('contacts', self::UID, 'archive-0001', 'NO');
            self::fail('Archive without confirmation was accepted.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $bridge->archiveRecord(
            'contacts',
            self::UID,
            'archive-0001',
            'ARCHIVE_REVIEWED_KONTOR_RECORD',
        );
        $bridge->archiveRecord(
            'contacts',
            self::UID,
            'archive-0001',
            'ARCHIVE_REVIEWED_KONTOR_RECORD',
        );

        self::assertSame(1, $resource->deleteCalls);
    }

    public function testUnknownResourcesAndFiltersAreRejected(): void
    {
        [$bridge] = $this->bridge();

        foreach (
            [
                fn () => $bridge->listRecords('missing'),
                fn () => $bridge->listRecords('contacts', filtersJson: '{"private":"x"}'),
            ] as $operation
        ) {
            try {
                $operation();
                self::fail('Invalid operation was accepted.');
            } catch (\InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    /** @return array{0: KontorMcpBridge, 1: FakeContactResource} */
    private function bridge(): array
    {
        $resource = new FakeContactResource();
        $resources = new ApiResourceRegistry();
        $resources->register($resource);

        return [
            new KontorMcpBridge(
                organizationUid: '01KYG385JXNQQ8H16SH37VNG3V',
                resources: $resources,
                components: new InMemoryComponentRegistry(),
                idempotency: new IdempotencyService(new InMemoryIdempotencyStore()),
            ),
            $resource,
        ];
    }
}

final class FakeContactResource implements ApiResourceInterface
{
    public int $createCalls = 0;
    public int $deleteCalls = 0;

    public function key(): string
    {
        return 'contacts';
    }

    public function schema(): ApiResourceSchema
    {
        return new ApiResourceSchema(
            fields: ['uid' => 'string', 'name' => 'string', 'status' => 'string', 'apiToken' => 'string'],
            filterableFields: ['status'],
            sortableFields: ['name'],
        );
    }

    public function list(string $organizationId, ApiQuery $query): ApiCollectionResult
    {
        return new ApiCollectionResult([
            ['uid' => KontorMcpBridgeTest::UID, 'name' => 'Ada', 'status' => 'active', 'apiToken' => 'hidden'],
        ], 1);
    }

    public function find(string $organizationId, string $uid): ?array
    {
        return ['uid' => $uid, 'name' => 'Ada', 'status' => 'active', 'apiToken' => 'hidden'];
    }

    public function create(string $organizationId, array $attributes): array
    {
        $this->createCalls++;

        return ['uid' => KontorMcpBridgeTest::UID, ...$attributes];
    }

    public function update(string $organizationId, string $uid, array $attributes): array
    {
        return ['uid' => $uid, ...$attributes];
    }

    public function delete(string $organizationId, string $uid): void
    {
        $this->deleteCalls++;
    }
}

final class InMemoryIdempotencyStore implements IdempotencyStoreInterface
{
    /** @var array<string, array{response: StoredIdempotentResponse, requestFingerprint: string}> */
    private array $records = [];

    public function find(string $organizationUid, string $idempotencyKey): ?array
    {
        return $this->records[$organizationUid . ':' . $idempotencyKey] ?? null;
    }

    public function store(
        string $organizationUid,
        string $idempotencyKey,
        string $requestFingerprint,
        int $responseStatus,
        array $responseBody,
        ?\DateTimeImmutable $expiresAt = null,
    ): void {
        $this->records[$organizationUid . ':' . $idempotencyKey] = [
            'response' => new StoredIdempotentResponse($responseStatus, $responseBody),
            'requestFingerprint' => $requestFingerprint,
        ];
    }
}

final class InMemoryComponentRegistry implements ComponentRegistryInterface
{
    public function markInstalled(string $name, string $version, ?string $source = null, ?string $checksum = null): void {}
    public function enable(string $name): void {}
    public function disable(string $name): void {}
    public function uninstall(string $name): void {}
    public function isEnabled(string $name): bool { return $name === 'contacts'; }
    public function find(string $name): ?array { return null; }

    public function all(): array
    {
        return [['name' => 'contacts', 'version' => '1', 'status' => 'enabled']];
    }
}
