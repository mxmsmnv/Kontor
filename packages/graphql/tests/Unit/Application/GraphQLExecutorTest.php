<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Tests\Unit\Application;

use Kontor\API\Application\ApiFieldProjector;
use Kontor\API\Contracts\ApiResourceInterface;
use Kontor\API\Domain\ApiToken;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\DTO\ApiResourceSchema;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\GraphQL\Application\GraphQLComplexityCalculator;
use Kontor\GraphQL\Application\GraphQLExecutor;
use Kontor\GraphQL\DTO\GraphQLDocument;
use Kontor\GraphQL\DTO\GraphQLSelection;
use PHPUnit\Framework\TestCase;

final class GraphQLExecutorTest extends TestCase
{
    private function token(array $scopes = []): ApiToken
    {
        return ApiToken::create('org_1', 'CI token', 'hash', $scopes);
    }

    public function test_finds_a_single_record_by_uid(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations', ['org_1' => ['uid' => 'org_1', 'name' => 'Acme']]));

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator());
        $document = new GraphQLDocument([new GraphQLSelection('organizations', ['uid' => 'org_1'], ['uid', 'name'])]);

        $result = $executor->execute($document, $this->token(['organizations:read']));

        $this->assertSame(['uid' => 'org_1', 'name' => 'Acme'], $result['data']['organizations']);
        $this->assertSame([], $result['errors']);
    }

    public function test_finding_a_missing_record_returns_null_for_that_key(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations'));

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator());
        $document = new GraphQLDocument([new GraphQLSelection('organizations', ['uid' => 'missing'], ['uid'])]);

        $result = $executor->execute($document, $this->token(['organizations:read']));

        $this->assertNull($result['data']['organizations']);
    }

    public function test_lists_records_and_projects_only_the_selected_fields(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations', ['org_1' => ['uid' => 'org_1', 'name' => 'Acme']]));

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator());
        $document = new GraphQLDocument([new GraphQLSelection('organizations', [], ['uid'])]);

        $result = $executor->execute($document, $this->token(['organizations:read']));

        $this->assertSame([['uid' => 'org_1']], $result['data']['organizations']);
    }

    public function test_an_unknown_resource_is_reported_as_an_error_without_aborting_other_selections(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations', ['org_1' => ['uid' => 'org_1']]));

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator());
        $document = new GraphQLDocument([
            new GraphQLSelection('not_a_resource', [], ['uid']),
            new GraphQLSelection('organizations', ['uid' => 'org_1'], ['uid']),
        ]);

        $result = $executor->execute($document, $this->token(['organizations:read']));

        $this->assertCount(1, $result['errors']);
        $this->assertSame('not_a_resource', $result['errors'][0]['path']);
        $this->assertSame(['uid' => 'org_1'], $result['data']['organizations']);
    }

    public function test_a_missing_scope_is_reported_as_an_error(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations', ['org_1' => ['uid' => 'org_1']]));

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator());
        $document = new GraphQLDocument([new GraphQLSelection('organizations', ['uid' => 'org_1'], ['uid'])]);

        $result = $executor->execute($document, $this->token(['widgets:read']));

        $this->assertArrayNotHasKey('organizations', $result['data']);
        $this->assertStringContainsString('organizations:read', $result['errors'][0]['message']);
    }

    public function test_an_unrestricted_token_has_every_scope(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations', ['org_1' => ['uid' => 'org_1']]));

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator());
        $document = new GraphQLDocument([new GraphQLSelection('organizations', ['uid' => 'org_1'], ['uid'])]);

        $result = $executor->execute($document, $this->token());

        $this->assertSame(['uid' => 'org_1'], $result['data']['organizations']);
    }

    public function test_a_resource_throwing_is_reported_as_an_error(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new class implements ApiResourceInterface {
            public function key(): string
            {
                return 'organizations';
            }

            public function schema(): ApiResourceSchema
            {
                return new ApiResourceSchema(fields: ['uid' => 'string']);
            }

            public function list(string $organizationId, ApiQuery $query): ApiCollectionResult
            {
                throw new \RuntimeException('boom');
            }

            public function find(string $organizationId, string $uid): ?array
            {
                throw new \RuntimeException('boom');
            }

            public function create(string $organizationId, array $attributes): array
            {
                throw new \RuntimeException('boom');
            }

            public function update(string $organizationId, string $uid, array $attributes): array
            {
                throw new \RuntimeException('boom');
            }

            public function delete(string $organizationId, string $uid): void
            {
                throw new \RuntimeException('boom');
            }
        });

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator());
        $document = new GraphQLDocument([new GraphQLSelection('organizations', [], ['uid'])]);

        $result = $executor->execute($document, $this->token(['organizations:read']));

        $this->assertSame('boom', $result['errors'][0]['message']);
    }

    public function test_a_query_exceeding_the_complexity_limit_is_rejected_entirely(): void
    {
        $resources = new ApiResourceRegistry();
        $resources->register(new FakeApiResource('organizations', ['org_1' => ['uid' => 'org_1']]));

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator(maxComplexity: 10));
        $document = new GraphQLDocument([new GraphQLSelection('organizations', ['pageSize' => 200], ['uid', 'name', 'status'])]);

        $result = $executor->execute($document, $this->token(['organizations:read']));

        $this->assertSame([], $result['data']);
        $this->assertStringContainsString('exceeds the maximum', $result['errors'][0]['message']);
    }
}
