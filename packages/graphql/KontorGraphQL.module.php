<?php

namespace ProcessWire;

use Kontor\API\Application\ApiFieldProjector;
use Kontor\API\DTO\ApiHttpRequest;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;
use Kontor\GraphQL\Application\GraphQLComplexityCalculator;
use Kontor\GraphQL\Application\GraphQLExecutor;
use Kontor\GraphQL\Application\GraphQLQueryParser;
use Kontor\GraphQL\Application\GraphQLRequestHandler;
use Kontor\GraphQL\Application\SchemaRegistry;
use Kontor\GraphQL\Health\GraphQLHealthCheck;

/**
 * KontorGraphQL bootstrap module (kontor.md Substage 8.2, second
 * component of Stage 8). Depends on both kontor/core and kontor/api —
 * every resource this package can query comes straight from KontorAPI's
 * own ApiResourceRegistry, so a resource registered once (by KontorAPI
 * itself or by a business component) is queryable through both REST and
 * GraphQL without KontorGraphQL maintaining a parallel resource list.
 *
 * Serves its own `/graphql` HTTP endpoint via a second
 * ProcessPageView::execute hook — deliberately not nested under
 * KontorAPI's `/api/kontor/v1/` prefix, both because that's the
 * universal GraphQL convention and to avoid any path collision with
 * KontorAPI's own hook, which this package never modifies.
 */
class KontorGraphQL extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor GraphQL',
            'summary' => 'Schema registry, component types, permission enforcement, complexity limits.',
            'version' => '002',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorGraphQL',
            'icon' => 'share-alt',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor', 'KontorAPI'],
        ];
    }

    private ?SchemaRegistry $schemaRegistry = null;
    private ?GraphQLRequestHandler $requestHandler = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));

        $this->addHookBefore('ProcessPageView::execute', $this, 'hookGraphQLRequest');
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__."/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorGraphQL', $language, $strings);
        }
    }

    /**
     * The real `/graphql` HTTP entry point. Only this thin translation
     * layer touches superglobals/`$this->wire()` — `GraphQLRequestHandler`
     * is plain-DTO and fully unit-tested. This method itself can only be
     * lint-checked in this sandbox, the same disclosed limitation as
     * `KontorAPI::hookApiRequest()` and every other ProcessWire-specific
     * hook glue in this monorepo.
     */
    public function hookGraphQLRequest(HookEvent $event): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = trim((string) (parse_url($requestUri, PHP_URL_PATH) ?? ''), '/');

        if ($path !== 'graphql') {
            return;
        }

        $headers = [];
        foreach (function_exists('getallheaders') ? (getallheaders() ?: []) : [] as $name => $value) {
            $headers[strtolower((string) $name)] = (string) $value;
        }

        $request = new ApiHttpRequest(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: $path,
            queryParams: [],
            headers: $headers,
            body: (string) file_get_contents('php://input'),
        );

        $response = $this->requestHandler()->handle($request);

        http_response_code($response->status);

        foreach ($response->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $response->body;

        $event->replace = true;
        $event->return = '';
    }

    public function schemaRegistry(): SchemaRegistry
    {
        return $this->schemaRegistry ??= new SchemaRegistry($this->apiModule()->resourceRegistry());
    }

    public function executor(): GraphQLExecutor
    {
        return new GraphQLExecutor($this->apiModule()->resourceRegistry(), new ApiFieldProjector(), new GraphQLComplexityCalculator());
    }

    public function requestHandler(): GraphQLRequestHandler
    {
        return $this->requestHandler ??= new GraphQLRequestHandler(
            new GraphQLQueryParser(),
            $this->executor(),
            $this->apiModule()->authenticator(),
        );
    }

    public function healthCheck(): GraphQLHealthCheck
    {
        return new GraphQLHealthCheck($this->schemaRegistry());
    }

    private function apiModule(): KontorAPI
    {
        /** @var KontorAPI $api */
        $api = $this->wire()->modules->get('KontorAPI');

        return $api;
    }

    public function ___install(): void
    {
        $components = new ComponentRegistry($this->wire()->database->pdo());
        $components->markInstalled('graphql', self::getModuleInfo()['version'], 'graphql');
        $components->enable('graphql');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->wire()->database->pdo());
        $components->markInstalled('graphql', self::getModuleInfo()['version'], 'graphql');
        $components->enable('graphql');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     * This package has no data of its own to keep — nothing to persist.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor GraphQL module removed.'));
    }
}
