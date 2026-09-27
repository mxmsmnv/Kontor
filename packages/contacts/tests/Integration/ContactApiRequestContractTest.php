<?php

declare(strict_types=1);

namespace Kontor\Contacts\Tests\Integration;

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
use Kontor\API\Migrations\Migration0001CreateApiTokensTable;
use Kontor\API\Migrations\Migration0004CreateIdempotencyKeysTable;
use Kontor\Contacts\Infrastructure\API\ContactResource;
use Kontor\Contacts\Infrastructure\Persistence\ContactRepository;
use Kontor\Core\Domain\Organization;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\SDK\ValueObjects\Uid;

final class ContactApiRequestContractTest extends DatabaseTestCase
{
    private ApiRequestHandler $handler;
    private TokenAuthenticator $authenticator;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        (new MigrationRunner($this->pdo))->run([
            new Migration0001CreateApiTokensTable(),
            new Migration0004CreateIdempotencyKeysTable(),
        ]);
        $organizations = new OrganizationRepository($this->pdo);
        $this->authenticator = new TokenAuthenticator(new ApiTokenRepository($this->pdo, $organizations));
        $this->token = $this->authenticator->issue(
            $this->organizationUid,
            'Contacts contract',
            ['contacts:read', 'contacts:write'],
        )->plaintext;
        $resources = new ApiResourceRegistry();
        $resources->register(new ContactResource(new ContactRepository($this->pdo, $organizations)));
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

    protected function tearDown(): void
    {
        if (isset($this->pdo)) {
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_idempotency_keys');
            $this->pdo->exec('DROP TABLE IF EXISTS kontor_api_tokens');
        }

        parent::tearDown();
    }

    public function test_documented_contacts_endpoint_is_authenticated_idempotent_and_tenant_scoped(): void
    {
        $body = json_encode([
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'email' => 'ADA@EXAMPLE.TEST',
        ], JSON_THROW_ON_ERROR);
        $first = $this->handler->handle($this->request(
            'POST',
            '/api/kontor/v1/contacts',
            body: $body,
            headers: ['idempotency-key' => 'contact-create-01'],
        ));
        $replayed = $this->handler->handle($this->request(
            'POST',
            '/api/kontor/v1/contacts',
            body: $body,
            headers: ['idempotency-key' => 'contact-create-01'],
        ));
        $created = json_decode($first->body, true, flags: JSON_THROW_ON_ERROR)['data'];
        $replayedData = json_decode($replayed->body, true, flags: JSON_THROW_ON_ERROR)['data'];

        $this->assertSame(201, $first->status);
        $this->assertSame(201, $replayed->status);
        $this->assertSame($created['uid'], $replayedData['uid']);
        $this->assertSame('ada@example.test', $created['email']);

        $list = $this->handler->handle($this->request(
            'GET',
            '/api/kontor/v1/contacts',
            query: ['fields' => ['contacts' => 'uid,displayName']],
        ));
        $listed = json_decode($list->body, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(200, $list->status);
        $this->assertSame(1, $listed['meta']['total']);
        $this->assertSame(['uid', 'displayName'], array_keys($listed['data'][0]));

        $other = new Organization(
            uid: Uid::generate(),
            name: 'Other tenant',
            legalName: null,
            countryCode: 'US',
            defaultLanguage: 'en',
            defaultCurrency: 'USD',
            timezone: 'UTC',
            status: 'active',
        );
        (new OrganizationRepository($this->pdo))->save($other);
        $otherToken = $this->authenticator->issue(
            $other->uid->toString(),
            'Other tenant contacts',
            ['contacts:read', 'contacts:write'],
        )->plaintext;
        $crossTenantFind = $this->handler->handle($this->request(
            'GET',
            '/api/kontor/v1/contacts/' . $created['uid'],
            token: $otherToken,
        ));
        $this->assertSame(404, $crossTenantFind->status);

        $updated = $this->handler->handle($this->request(
            'PATCH',
            '/api/kontor/v1/contacts/' . $created['uid'],
            body: json_encode(['firstName' => 'Augusta Ada'], JSON_THROW_ON_ERROR),
        ));
        $updatedData = json_decode($updated->body, true, flags: JSON_THROW_ON_ERROR)['data'];
        $this->assertSame(200, $updated->status);
        $this->assertSame('Augusta Ada Lovelace', $updatedData['displayName']);

        $deleted = $this->handler->handle($this->request(
            'DELETE',
            '/api/kontor/v1/contacts/' . $created['uid'],
        ));
        $missing = $this->handler->handle($this->request(
            'GET',
            '/api/kontor/v1/contacts/' . $created['uid'],
        ));
        $this->assertSame(204, $deleted->status);
        $this->assertSame(404, $missing->status);
    }

    /** @param array<string, mixed> $query @param array<string, string> $headers */
    private function request(
        string $method,
        string $path,
        array $query = [],
        string $body = '',
        array $headers = [],
        ?string $token = null,
    ): ApiHttpRequest {
        return new ApiHttpRequest(
            $method,
            $path,
            $query,
            array_merge(['authorization' => 'Bearer ' . ($token ?? $this->token)], $headers),
            $body,
        );
    }
}
