<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\Contracts\ApiResourceInterface;
use Kontor\API\DTO\ApiCollectionResult;
use Kontor\API\DTO\ApiHttpRequest;
use Kontor\API\DTO\ApiHttpResponse;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;

/**
 * The single true entry point for `/api/kontor/v1/...` requests
 * (kontor.md#20). Takes and returns plain DTOs — no superglobals, no
 * `$this->wire()` — so the whole request/response cycle (auth, routing,
 * query parsing, CRUD dispatch, idempotency, error envelopes) is
 * unit-testable without a live HTTP server or ProcessWire bootstrap.
 * `KontorAPI.module.php`'s hook is a thin translator from a real request
 * to an `ApiHttpRequest` and back from `ApiHttpResponse` to real headers
 * and output — the same "pure core, thin I/O wrapper" split used
 * throughout this monorepo for testability.
 */
final class ApiRequestHandler
{
    public function __construct(
        private readonly ApiRouter $router,
        private readonly ApiResourceRegistry $resources,
        private readonly RequestQueryParser $queryParser,
        private readonly ApiFieldProjector $projector,
        private readonly ApiResponseFactory $responses,
        private readonly TokenAuthenticator $authenticator,
        private readonly IdempotencyService $idempotency,
    ) {
    }

    public function handle(ApiHttpRequest $request): ApiHttpResponse
    {
        $requestId = $this->responses->newRequestId();
        $route = $this->router->match($request->method, $request->path);

        if ($route === null) {
            return $this->error(404, 'route_not_found', 'No API route matches this request.', $requestId);
        }

        if (!$this->resources->has($route->resourceKey)) {
            return $this->error(404, 'resource_not_found', "Resource \"{$route->resourceKey}\" does not exist.", $requestId);
        }

        try {
            $access = in_array($route->action, ['list', 'find'], true) ? 'read' : 'write';
            $token = $this->authenticator->authenticate(
                $this->bearerToken($request),
                "{$route->resourceKey}:{$access}",
            );
        } catch (AuthenticationFailedException $e) {
            return $this->error(401, 'unauthenticated', $e->getMessage(), $requestId);
        }

        $resource = $this->resources->get($route->resourceKey);
        $organizationId = $token->organizationId;

        try {
            return match ($route->action) {
                'list' => $this->handleList($request, $resource, $route->resourceKey, $organizationId, $requestId),
                'find' => $this->handleFind($request, $resource, $route->resourceKey, $organizationId, $route->uid, $requestId),
                'create' => $this->handleCreate($request, $resource, $organizationId, $requestId),
                'update' => $this->handleUpdate($request, $resource, $organizationId, $route->uid, $requestId),
                'delete' => $this->handleDelete($resource, $organizationId, $route->uid, $requestId),
                default => $this->error(405, 'method_not_allowed', 'This method is not supported for this route.', $requestId),
            };
        } catch (UnsupportedResourceOperationException $e) {
            return $this->error(405, 'unsupported_operation', $e->getMessage(), $requestId);
        } catch (IdempotencyKeyConflictException $e) {
            return $this->error(409, 'idempotency_key_conflict', $e->getMessage(), $requestId);
        } catch (\JsonException) {
            return $this->error(400, 'invalid_json', 'The request body is not valid JSON.', $requestId);
        } catch (\RuntimeException $e) {
            return $this->error(404, 'not_found', $e->getMessage(), $requestId);
        }
    }

    private function handleList(ApiHttpRequest $request, ApiResourceInterface $resource, string $resourceKey, string $organizationId, string $requestId): ApiHttpResponse
    {
        $query = $this->queryParser->parse($request->queryParams, $resourceKey);
        $result = $resource->list($organizationId, $query);

        $rows = array_map(fn (array $row) => $this->projector->project($row, $query->fields), $result->rows);

        return ApiHttpResponse::json(200, $this->responses->collection(new ApiCollectionResult($rows, $result->total), $query, $requestId));
    }

    private function handleFind(ApiHttpRequest $request, ApiResourceInterface $resource, string $resourceKey, string $organizationId, ?string $uid, string $requestId): ApiHttpResponse
    {
        $row = $resource->find($organizationId, (string) $uid);

        if ($row === null) {
            return $this->error(404, 'not_found', 'Resource not found.', $requestId);
        }

        $query = $this->queryParser->parse($request->queryParams, $resourceKey);

        return ApiHttpResponse::json(200, $this->responses->success($this->projector->project($row, $query->fields), $requestId));
    }

    private function handleCreate(ApiHttpRequest $request, ApiResourceInterface $resource, string $organizationId, string $requestId): ApiHttpResponse
    {
        $attributes = $this->decodeBody($request->body);
        $idempotencyKey = $request->header('Idempotency-Key');

        /** @var callable(): array{status: int, body: array<string, mixed>} $operation */
        $operation = static fn (): array => ['status' => 201, 'body' => $resource->create($organizationId, $attributes)];

        if ($idempotencyKey === null) {
            $result = $operation();

            return ApiHttpResponse::json($result['status'], $this->responses->success($result['body'], $requestId));
        }

        $stored = $this->idempotency->remember($organizationId, $idempotencyKey, $attributes, $operation);

        return ApiHttpResponse::json($stored->status, $this->responses->success($stored->body, $requestId));
    }

    private function handleUpdate(ApiHttpRequest $request, ApiResourceInterface $resource, string $organizationId, ?string $uid, string $requestId): ApiHttpResponse
    {
        $attributes = $this->decodeBody($request->body);
        $updated = $resource->update($organizationId, (string) $uid, $attributes);

        return ApiHttpResponse::json(200, $this->responses->success($updated, $requestId));
    }

    private function handleDelete(ApiResourceInterface $resource, string $organizationId, ?string $uid, string $requestId): ApiHttpResponse
    {
        $resource->delete($organizationId, (string) $uid);

        return ApiHttpResponse::noContent(204);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(string $body): array
    {
        if (trim($body) === '') {
            return [];
        }

        return json_decode($body, associative: true, flags: JSON_THROW_ON_ERROR);
    }

    private function bearerToken(ApiHttpRequest $request): string
    {
        $header = $request->header('Authorization') ?? '';

        return str_starts_with($header, 'Bearer ') ? substr($header, 7) : $header;
    }

    private function error(int $status, string $code, string $message, string $requestId): ApiHttpResponse
    {
        return ApiHttpResponse::json($status, $this->responses->error($code, $message, [], $requestId));
    }
}
