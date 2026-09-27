<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\DTO\ApiRouteMatch;

/**
 * Matches an HTTP method and path under kontor.md#20's
 * `/api/kontor/v1/` prefix to a registered resource and CRUD action.
 * Pure — no HTTP dependency, so it runs as a real unit test.
 *
 * Only the five standard CRUD routes are matched
 * (`GET/POST /resource`, `GET/PATCH/DELETE /resource/{uid}`).
 * kontor.md#20.10's own sub-action routes (`POST /leads/{uid}/convert`,
 * `POST /invoices/{uid}/issue`, etc.) are a business component's own
 * concern to add when it registers its resource — out of scope for this
 * substage's generic router, the same "not retrofitted" discipline used
 * throughout this monorepo's registries.
 */
final class ApiRouter
{
    private const PREFIX = 'api/kontor/v1';

    public function match(string $method, string $path): ?ApiRouteMatch
    {
        $path = trim(parse_url($path, PHP_URL_PATH) ?? $path, '/');

        if (!str_starts_with($path, self::PREFIX)) {
            return null;
        }

        $remainder = trim(substr($path, strlen(self::PREFIX)), '/');

        if ($remainder === '') {
            return null;
        }

        $segments = explode('/', $remainder);
        $resourceKey = array_shift($segments);

        if (count($segments) > 1) {
            return null;
        }

        $uid = $segments[0] ?? null;

        return match (strtoupper($method)) {
            'GET' => new ApiRouteMatch($resourceKey, $uid !== null ? 'find' : 'list', $uid),
            'POST' => $uid === null ? new ApiRouteMatch($resourceKey, 'create', null) : null,
            'PATCH' => $uid !== null ? new ApiRouteMatch($resourceKey, 'update', $uid) : null,
            'DELETE' => $uid !== null ? new ApiRouteMatch($resourceKey, 'delete', $uid) : null,
            default => null,
        };
    }
}
