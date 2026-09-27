<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Integration;

use Kontor\API\Application\ApiFieldProjector;
use Kontor\API\Application\ApiRequestHandler;
use Kontor\API\Application\ApiResponseFactory;
use Kontor\API\Application\ApiRouter;
use Kontor\API\Application\IdempotencyService;
use Kontor\API\Application\RequestQueryParser;
use Kontor\API\Application\TokenAuthenticator;
use Kontor\API\DTO\ApiHttpRequest;
use Kontor\API\Infrastructure\Persistence\ApiTokenRepository;
use Kontor\API\Infrastructure\Persistence\IdempotencyKeyRepository;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\API\Infrastructure\Resources\OrganizationResource;
use Kontor\API\Migrations\Migration0001CreateApiTokensTable;
use Kontor\API\Migrations\Migration0004CreateIdempotencyKeysTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;

/**
 * An end-to-end pass through every milestone of this substage at once:
 * authentication, CRUD resources, filtering (sparse fields), and
 * idempotency, driven entirely through `ApiRequestHandler::handle()` —
 * the same entry point `KontorAPI::hookApiRequest()` calls for a real
 * HTTP request.
 */
final class ApiRequestHandlerTest extends DatabaseTestCase
{
    private TokenAuthenticator $authenticator;
    private ApiRequestHandler $handler;
    private string $plaintextToken;

    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0001CreateApiTokensTable(),
            new Migration0004CreateIdempotencyKeysTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_idempotency_keys', 'kontor_api_tokens', 'kontor_organizations', 'kontor_migrations'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $organizations = new OrganizationRepository($this->pdo);
        $this->authenticator = new TokenAuthenticator(new ApiTokenRepository($this->pdo, $organizations));
        $issued = $this->authenticator->issue($this->organizationUid, 'CI token');
        $this->plaintextToken = $issued->plaintext;

        $resources = new ApiResourceRegistry();
        $resources->register(new OrganizationResource($organizations));

        $this->handler = new ApiRequestHandler(
            new ApiRouter(),
            $resources,
            new RequestQueryParser(),
            new ApiFieldProjector(),
            new ApiResponseFactory(),
            $this->authenticator,
            new IdempotencyService(new IdempotencyKeyRepository($this->pdo, $organizations)),
        );
    }

    private function request(string $method, string $path, array $query = [], string $body = '', array $headers = []): ApiHttpRequest
    {
        return new ApiHttpRequest($method, $path, $query, array_merge(['authorization' => "Bearer {$this->plaintextToken}"], $headers), $body);
    }

    public function test_list_returns_the_callers_organization(): void
    {
        $response = $this->handler->handle($this->request('GET', '/api/kontor/v1/organizations'));

        $this->assertSame(200, $response->status);
        $data = json_decode($response->body, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['data']);
        $this->assertSame($this->organizationUid, $data['data'][0]['uid']);
        $this->assertSame(1, $data['meta']['total']);
    }

    public function test_sparse_fields_are_applied(): void
    {
        $response = $this->handler->handle($this->request('GET', '/api/kontor/v1/organizations', ['fields' => ['organizations' => 'uid,name']]));

        $data = json_decode($response->body, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['uid', 'name'], array_keys($data['data'][0]));
    }

    public function test_find_and_update(): void
    {
        $update = $this->handler->handle($this->request(
            'PATCH',
            "/api/kontor/v1/organizations/{$this->organizationUid}",
            body: json_encode(['name' => 'Acme Corp'], JSON_THROW_ON_ERROR),
        ));

        $this->assertSame(200, $update->status);

        $find = $this->handler->handle($this->request('GET', "/api/kontor/v1/organizations/{$this->organizationUid}"));
        $data = json_decode($find->body, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('Acme Corp', $data['data']['name']);
    }

    public function test_create_is_rejected_as_unsupported(): void
    {
        $response = $this->handler->handle($this->request('POST', '/api/kontor/v1/organizations', body: '{}'));

        $this->assertSame(405, $response->status);
        $error = json_decode($response->body, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('unsupported_operation', $error['error']['code']);
    }

    public function test_unknown_resource_is_404(): void
    {
        $response = $this->handler->handle($this->request('GET', '/api/kontor/v1/not-a-resource'));

        $this->assertSame(404, $response->status);
    }

    public function test_unknown_route_is_404(): void
    {
        $response = $this->handler->handle($this->request('GET', '/not/the/api/prefix'));

        $this->assertSame(404, $response->status);
    }

    public function test_invalid_token_is_401(): void
    {
        $response = $this->handler->handle(new ApiHttpRequest('GET', '/api/kontor/v1/organizations', [], ['authorization' => 'Bearer garbage'], ''));

        $this->assertSame(401, $response->status);
    }

    public function test_a_scoped_token_cannot_cross_access_level(): void
    {
        $readOnly = $this->authenticator->issue(
            $this->organizationUid,
            'Read-only CI token',
            ['organizations:read'],
        );

        $read = $this->handler->handle($this->request(
            'GET',
            '/api/kontor/v1/organizations',
            headers: ['authorization' => "Bearer {$readOnly->plaintext}"],
        ));
        $write = $this->handler->handle($this->request(
            'PATCH',
            "/api/kontor/v1/organizations/{$this->organizationUid}",
            body: json_encode(['name' => 'Forbidden'], JSON_THROW_ON_ERROR),
            headers: ['authorization' => "Bearer {$readOnly->plaintext}"],
        ));

        $this->assertSame(200, $read->status);
        $this->assertSame(401, $write->status);
        $error = json_decode($write->body, associative: true, flags: JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('organizations:write', $error['error']['message']);
    }

    public function test_create_with_an_idempotency_key_still_reports_unsupported_on_first_and_every_call(): void
    {
        // organizations doesn't support create at all, so the idempotency
        // path never gets to cache anything — confirms the unsupported
        // exception surfaces even when an Idempotency-Key is present.
        $first = $this->handler->handle($this->request('POST', '/api/kontor/v1/organizations', body: '{}', headers: ['Idempotency-Key' => 'key-1']));
        $second = $this->handler->handle($this->request('POST', '/api/kontor/v1/organizations', body: '{}', headers: ['Idempotency-Key' => 'key-1']));

        $this->assertSame(405, $first->status);
        $this->assertSame(405, $second->status);
    }
}
