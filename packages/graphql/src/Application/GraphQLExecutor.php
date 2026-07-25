<?php

declare(strict_types=1);

namespace Kontor\GraphQL\Application;

use Kontor\API\Application\ApiFieldProjector;
use Kontor\API\Domain\ApiToken;
use Kontor\API\DTO\ApiQuery;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\GraphQL\DTO\GraphQLDocument;

/**
 * Resolves a parsed `GraphQLDocument` against `kontor/api`'s own
 * `ApiResourceRegistry` — reusing its resources, `ApiQuery`, and
 * `ApiFieldProjector` directly rather than a parallel data-access layer,
 * the same real cross-package reuse `kontor/invoices` made of
 * `kontor/sales`'s document lines.
 *
 * One selection failing (unknown resource, missing scope, a resource
 * throwing) does not abort the whole query — it's reported in `errors`
 * against that selection's own result key, the same "one failure
 * doesn't stop the rest" behavior `Kontor\Core\Infrastructure\Events\EventDispatcher`
 * and `kontor/automation`'s engine already use for their own multi-item
 * processing.
 *
 * The "permission enforcement" milestone: a selection only resolves if
 * the authenticated token has a `"{resourceKey}:read"` scope — reusing
 * `ApiToken::hasScope()` from `kontor/api` rather than a second
 * permission system.
 */
final class GraphQLExecutor
{
    public function __construct(
        private readonly ApiResourceRegistry $resources,
        private readonly ApiFieldProjector $projector,
        private readonly GraphQLComplexityCalculator $complexity,
    ) {
    }

    /**
     * @return array{data: array<string, mixed>, errors: list<array{path: string, message: string}>}
     */
    public function execute(GraphQLDocument $document, ApiToken $token): array
    {
        if ($this->complexity->exceedsLimit($document)) {
            return [
                'data' => [],
                'errors' => [[
                    'path' => '',
                    'message' => "Query complexity {$this->complexity->complexityOf($document)} exceeds the maximum of {$this->complexity->maxComplexity()}.",
                ]],
            ];
        }

        $data = [];
        $errors = [];

        foreach ($document->selections as $selection) {
            if (!$this->resources->has($selection->resourceKey)) {
                $errors[] = ['path' => $selection->resourceKey, 'message' => "Unknown resource \"{$selection->resourceKey}\"."];

                continue;
            }

            if (!$token->hasScope("{$selection->resourceKey}:read")) {
                $errors[] = ['path' => $selection->resourceKey, 'message' => "Missing scope \"{$selection->resourceKey}:read\"."];

                continue;
            }

            $resource = $this->resources->get($selection->resourceKey);

            try {
                if (isset($selection->arguments['uid'])) {
                    $row = $resource->find($token->organizationId, (string) $selection->arguments['uid']);
                    $data[$selection->resourceKey] = $row !== null ? $this->projector->project($row, $selection->fields) : null;
                } else {
                    $query = new ApiQuery(
                        page: (int) ($selection->arguments['page'] ?? 1),
                        pageSize: (int) ($selection->arguments['pageSize'] ?? 50),
                    );
                    $result = $resource->list($token->organizationId, $query);
                    $data[$selection->resourceKey] = array_map(
                        fn (array $row) => $this->projector->project($row, $selection->fields),
                        $result->rows,
                    );
                }
            } catch (\Throwable $e) {
                $errors[] = ['path' => $selection->resourceKey, 'message' => $e->getMessage()];
            }
        }

        return ['data' => $data, 'errors' => $errors];
    }
}
