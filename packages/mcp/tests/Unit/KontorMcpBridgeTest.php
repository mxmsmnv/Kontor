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
use Kontor\SDK\Contracts\SearchProviderInterface;
use Kontor\SDK\DTO\SearchHit;
use Kontor\SDK\DTO\SearchQuery;
use Kontor\SDK\DTO\SearchResult;
use Kontor\Settings\Contracts\SettingsMigrationInterface;
use Kontor\Settings\DTO\ProviderMigrationResult;
use Kontor\Settings\DTO\SettingsMigrationReport;
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

    public function testSearchForwardsABoundedScopedQueryAndMapsHits(): void
    {
        $search = new FakeSearchProvider();
        [$bridge] = $this->bridge($search);

        $result = $bridge->search('  ada  ', '["contact","contact"]', 500, -10);

        self::assertNotNull($search->query);
        self::assertSame('01KYG385JXNQQ8H16SH37VNG3V', $search->query->organizationId);
        self::assertSame('ada', $search->query->term);
        self::assertSame(['contact'], $search->query->entityTypes);
        self::assertSame(50, $search->query->limit);
        self::assertSame(0, $search->query->offset);
        self::assertSame(1, $result['total']);
        self::assertSame([
            'entity_type' => 'contact',
            'uid' => self::UID,
            'title' => 'Ada Lovelace',
            'subtitle' => 'Customer',
            'admin_url' => '/kontor/contacts/' . self::UID,
            'score' => 0.95,
        ], $result['items'][0]);
    }

    public function testGetProjectsFieldsAndUpdateIsReplaySafe(): void
    {
        [$bridge, $resource] = $this->bridge();

        $found = $bridge->getRecord('contacts', self::UID, '["uid","name"]');
        $first = $bridge->updateRecord('contacts', self::UID, '{"name":"Grace"}', 'update-0001');
        $second = $bridge->updateRecord('contacts', self::UID, '{"name":"Grace"}', 'update-0001');

        self::assertSame(['uid' => self::UID, 'name' => 'Ada'], $found['record']);
        self::assertArrayNotHasKey('apiToken', $found['record']);
        self::assertSame($first, $second);
        self::assertSame(['uid' => self::UID, 'name' => 'Grace'], $first['record']);
        self::assertSame(1, $resource->updateCalls);
    }

    public function testSettingsExportPreviewAndConfirmedApplyAreReplaySafe(): void
    {
        $settings = new FakeSettingsMigration();
        [$bridge] = $this->bridge(settings: $settings);
        $document = json_encode($settings->profile, JSON_THROW_ON_ERROR);

        self::assertSame($settings->profile, $bridge->exportSettings(['transport' => 'mcp']));
        $preview = $bridge->previewSettings($document);
        self::assertTrue($preview['successful']);
        self::assertFalse($preview['applied']);

        try {
            $bridge->applySettings($document, 'settings-0001', 'NO');
            self::fail('Settings apply without confirmation was accepted.');
        } catch (\InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $first = $bridge->applySettings(
            $document,
            'settings-0001',
            'APPLY_REVIEWED_KONTOR_SETTINGS',
        );
        $second = $bridge->applySettings(
            $document,
            'settings-0001',
            'APPLY_REVIEWED_KONTOR_SETTINGS',
        );

        self::assertSame($first, $second);
        self::assertTrue($first['applied']);
        self::assertTrue($first['replay_safe']);
        self::assertSame(1, $settings->exportCalls);
        self::assertSame(1, $settings->applyCalls);
    }

    /** @return array{0: KontorMcpBridge, 1: FakeContactResource} */
    private function bridge(
        ?SearchProviderInterface $search = null,
        ?SettingsMigrationInterface $settings = null,
    ): array
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
                search: $search,
                settings: $settings,
            ),
            $resource,
        ];
    }
}

final class FakeContactResource implements ApiResourceInterface
{
    public int $createCalls = 0;
    public int $updateCalls = 0;
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
        $this->updateCalls++;

        return ['uid' => $uid, ...$attributes, 'apiToken' => 'hidden'];
    }

    public function delete(string $organizationId, string $uid): void
    {
        $this->deleteCalls++;
    }
}

final class FakeSearchProvider implements SearchProviderInterface
{
    public ?SearchQuery $query = null;

    public function name(): string
    {
        return 'fake-search';
    }

    public function supports(string $entityType): bool
    {
        return $entityType === 'contact';
    }

    public function search(SearchQuery $query): SearchResult
    {
        $this->query = $query;

        return new SearchResult([
            new SearchHit(
                entityType: 'contact',
                entityUid: KontorMcpBridgeTest::UID,
                title: 'Ada Lovelace',
                subtitle: 'Customer',
                url: '/kontor/contacts/' . KontorMcpBridgeTest::UID,
                score: 0.95,
            ),
        ], 1);
    }
}

final class FakeSettingsMigration implements SettingsMigrationInterface
{
    public int $exportCalls = 0;
    public int $applyCalls = 0;

    /** @var array<string, mixed> */
    public array $profile = [
        '$schema' => 'https://kontor.dev/schema/settings-profile.v1.json',
        'schemaVersion' => 1,
        'product' => 'Kontor',
        'providers' => [
            'organization' => [
                'schemaVersion' => 1,
                'data' => ['timezone' => 'UTC'],
            ],
        ],
    ];

    public function exportProfile(array $source = []): array
    {
        $this->exportCalls++;

        return $this->profile;
    }

    public function decode(string $json): array
    {
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    public function preview(array $profile): SettingsMigrationReport
    {
        return $this->report(applied: false);
    }

    public function apply(array $profile): SettingsMigrationReport
    {
        $this->applyCalls++;

        return $this->report(applied: true);
    }

    private function report(bool $applied): SettingsMigrationReport
    {
        return new SettingsMigrationReport(
            fingerprint: hash('sha256', json_encode($this->profile, JSON_THROW_ON_ERROR)),
            applied: $applied,
            providers: [new ProviderMigrationResult(
                key: 'organization',
                label: 'Organization',
                status: $applied ? 'applied' : 'ready',
                changes: [[
                    'field' => 'timezone',
                    'label' => 'Timezone',
                    'from' => 'America/New_York',
                    'to' => 'UTC',
                ]],
            )],
        );
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
