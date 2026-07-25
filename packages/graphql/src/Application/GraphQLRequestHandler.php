<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Application;

use Kontor\API\Application\AuthenticationFailedException;
use Kontor\API\Application\TokenAuthenticator;
use Kontor\API\DTO\ApiHttpRequest;
use Kontor\API\DTO\ApiHttpResponse;

/**
 * The single true entry point for GraphQL requests, mirroring
 * `kontor/api`'s own `ApiRequestHandler` — plain DTOs in and out, no
 * superglobals, so the whole parse/authenticate/execute cycle is
 * unit-testable without a live HTTP server.
 *
 * Deliberately served at its own `/graphql` path rather than nested under
 * `kontor/api`'s `/api/kontor/v1/` prefix: that's the universal
 * convention real GraphQL APIs already follow, and it avoids any
 * path-prefix collision with `KontorAPI::hookApiRequest()`'s own hook on
 * `ProcessPageView::execute` — this package never modifies that
 * already-shipped hook to special-case a `/graphql` sub-path.
 */
final class GraphQLRequestHandler
{
    public function __construct(
        private readonly GraphQLQueryParser $parser,
        private readonly GraphQLExecutor $executor,
        private readonly TokenAuthenticator $authenticator,
    ) {
    }

    public function handle(ApiHttpRequest $request): ApiHttpResponse
    {
        if (strtoupper($request->method) !== 'POST') {
            return ApiHttpResponse::json(405, ['errors' => [['message' => 'GraphQL requests must use POST.']]]);
        }

        try {
            $token = $this->authenticator->authenticate($this->bearerToken($request));
        } catch (AuthenticationFailedException $e) {
            return ApiHttpResponse::json(401, ['errors' => [['message' => $e->getMessage()]]]);
        }

        try {
            $body = json_decode($request->body, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ApiHttpResponse::json(400, ['errors' => [['message' => 'The request body is not valid JSON.']]]);
        }

        try {
            $document = $this->parser->parse((string) ($body['query'] ?? ''));
        } catch (GraphQLSyntaxException $e) {
            return ApiHttpResponse::json(400, ['errors' => [['message' => $e->getMessage()]]]);
        }

        return ApiHttpResponse::json(200, $this->executor->execute($document, $token));
    }

    private function bearerToken(ApiHttpRequest $request): string
    {
        $header = $request->header('Authorization') ?? '';

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : $header;
    }
}
