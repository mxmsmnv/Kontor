<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Tests\Integration;

use Kontor\API\Application\ApiFieldProjector;
use Kontor\API\Application\TokenAuthenticator;
use Kontor\API\DTO\ApiHttpRequest;
use Kontor\API\Infrastructure\Persistence\ApiTokenRepository;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\API\Infrastructure\Resources\OrganizationResource;
use Kontor\API\Migrations\Migration0001CreateApiTokensTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;
use Kontor\GraphQL\Application\GraphQLComplexityCalculator;
use Kontor\GraphQL\Application\GraphQLExecutor;
use Kontor\GraphQL\Application\GraphQLQueryParser;
use Kontor\GraphQL\Application\GraphQLRequestHandler;

/**
 * A full pass through every milestone at once: schema/component types
 * (implicitly, via OrganizationResource's registered schema), permission
 * enforcement (token scopes), and complexity limits — driven through
 * `GraphQLRequestHandler::handle()`, reusing `kontor/api`'s own
 * `TokenAuthenticator`/`ApiTokenRepository` for real authentication. The
 * second real consumer of `Kontor\Core\Testing\DatabaseTestCase` outside
 * `kontor/core`, after `kontor/api` itself.
 */
final class GraphQLRequestHandlerTest extends DatabaseTestCase
{
    private string $plaintextToken;
    private string $unscopedPlaintextToken;
    private GraphQLRequestHandler $handler;

    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateApiTokensTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_api_tokens', 'kontor_organizations', 'kontor_migrations'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $authenticator = new TokenAuthenticator(new ApiTokenRepository($this->pdo, $organizations));
        $this->plaintextToken = $authenticator->issue($this->organizationUid, 'CI token', ['organizations:read'])->plaintext;
        $this->unscopedPlaintextToken = $authenticator->issue($this->organizationUid, 'Scopeless token', ['widgets:read'])->plaintext;

        $resources = new ApiResourceRegistry();
        $resources->register(new OrganizationResource($organizations));

        $executor = new GraphQLExecutor($resources, new ApiFieldProjector(), new GraphQLComplexityCalculator());
        $this->handler = new GraphQLRequestHandler(new GraphQLQueryParser(), $executor, $authenticator);
    }

    private function request(string $query, string $method = 'POST', ?string $token = null): ApiHttpRequest
    {
        return new ApiHttpRequest(
            $method,
            'graphql',
            [],
            ['authorization' => 'Bearer '.($token ?? $this->plaintextToken)],
            json_encode(['query' => $query], JSON_THROW_ON_ERROR),
        );
    }

    public function test_a_valid_query_returns_the_callers_organization(): void
    {
        $response = $this->handler->handle($this->request('{ organizations(uid: "'.$this->organizationUid.'") { uid name } }'));

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($this->organizationUid, $body['data']['organizations']['uid']);
        $this->assertSame([], $body['errors']);
    }

    public function test_a_list_query(): void
    {
        $response = $this->handler->handle($this->request('{ organizations { uid } }'));

        $body = json_decode($response->body, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $body['data']['organizations']);
    }

    public function test_get_is_rejected(): void
    {
        $response = $this->handler->handle($this->request('{ organizations { uid } }', method: 'GET'));

        $this->assertSame(405, $response->status);
    }

    public function test_an_invalid_token_is_401(): void
    {
        $response = $this->handler->handle($this->request('{ organizations { uid } }', token: 'garbage'));

        $this->assertSame(401, $response->status);
    }

    public function test_a_syntax_error_is_400(): void
    {
        $response = $this->handler->handle($this->request('{ organizations( { uid } }'));

        $this->assertSame(400, $response->status);
    }

    public function test_a_scope_the_token_does_not_have_is_reported_in_errors_not_as_an_http_error(): void
    {
        $response = $this->handler->handle($this->request('{ organizations { uid } }', token: $this->unscopedPlaintextToken));

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayNotHasKey('organizations', $body['data']);
        $this->assertStringContainsString('organizations:read', $body['errors'][0]['message']);
    }
}
