<?php

namespace ProcessWire;

use Kontor\API\Application\ApiFieldProjector;
use Kontor\API\Application\ApiRequestHandler;
use Kontor\API\Application\ApiResponseFactory;
use Kontor\API\Application\ApiRouter;
use Kontor\API\Application\IdempotencyService;
use Kontor\API\Application\OpenApiGenerator;
use Kontor\API\Application\RequestQueryParser;
use Kontor\API\Application\TokenAuthenticator;
use Kontor\API\Application\WebhookDeliveryService;
use Kontor\API\Application\WebhookDispatcher;
use Kontor\API\DTO\ApiHttpRequest;
use Kontor\API\Health\ApiHealthCheck;
use Kontor\API\Infrastructure\Http\CurlHttpClient;
use Kontor\API\Infrastructure\Persistence\ApiTokenRepository;
use Kontor\API\Infrastructure\Persistence\IdempotencyKeyRepository;
use Kontor\API\Infrastructure\Persistence\WebhookDeliveryRepository;
use Kontor\API\Infrastructure\Persistence\WebhookSubscriptionRepository;
use Kontor\API\Infrastructure\Registry\ApiResourceRegistry;
use Kontor\API\Infrastructure\Resources\OrganizationResource;
use Kontor\API\Migrations\Migration0001CreateApiTokensTable;
use Kontor\API\Migrations\Migration0002CreateWebhookSubscriptionsTable;
use Kontor\API\Migrations\Migration0003CreateWebhookDeliveriesTable;
use Kontor\API\Migrations\Migration0004CreateIdempotencyKeysTable;
use Kontor\Core\Infrastructure\Events\EventDispatcher;
use Kontor\Core\Infrastructure\Migrations\MigrationRunner;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Infrastructure\Registry\ComponentRegistry;
use Kontor\Core\Infrastructure\Registry\TranslationRegistry;

/**
 * KontorAPI bootstrap module (kontor.md Substage 8.1, first component of
 * Stage 8). Depends only on kontor/core; business components register
 * their own `ApiResourceInterface` implementations into
 * `ApiResourceRegistry` without this package ever depending on them, the
 * same inverted-dependency shape every registry in this monorepo already
 * uses. `init()` wires `WebhookDispatcher::handleEvent()` onto Core's
 * real `EventDispatcher` for every distinct active webhook subscription's
 * event, the same "distinct triggers" approach `kontor/automation`
 * already established, and hooks the real `/api/kontor/v1/` HTTP entry
 * point via `ProcessPageView::execute`.
 */
class KontorAPI extends WireData implements Module
{
    public static function getModuleInfo(): array
    {
        return [
            'title' => 'Kontor API',
            'summary' => 'Authentication, CRUD resource registry, filtering, OpenAPI, webhooks, idempotency.',
            'version' => '002',
            'author' => 'Maxim Semenov',
            'href' => 'https://github.com/mxmsmnv/KontorAPI',
            'icon' => 'plug',
            'singular' => true,
            'autoload' => true,
            'requires' => ['Kontor'],
            'permissions' => [
                'kontor-api-token-manage' => 'Issue and revoke API tokens',
                'kontor-api-webhook-manage' => 'Manage webhook subscriptions',
            ],
        ];
    }

    private ?ApiTokenRepository $tokenRepository = null;
    private ?WebhookSubscriptionRepository $webhookSubscriptionRepository = null;
    private ?WebhookDeliveryRepository $webhookDeliveryRepository = null;
    private ?IdempotencyKeyRepository $idempotencyKeyRepository = null;
    private ?ApiResourceRegistry $resourceRegistry = null;
    private ?TokenAuthenticator $authenticator = null;
    private ?WebhookDeliveryService $webhookDeliveryService = null;
    private ?WebhookDispatcher $webhookDispatcher = null;
    private ?ApiRequestHandler $requestHandler = null;

    public function init(): void
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        $this->registerTranslations($kontor->container()->get(TranslationRegistry::class));
        $this->resourceRegistry()->register(new OrganizationResource($this->organizations()));
        $this->subscribeToWebhookEvents($kontor->container()->get(EventDispatcher::class));

        $this->addHookBefore('ProcessPageView::execute', $this, 'hookApiRequest');
    }

    private function subscribeToWebhookEvents(EventDispatcher $dispatcher): void
    {
        foreach ($this->webhookSubscriptionRepository()->distinctActiveEventPatterns() as $eventName) {
            $dispatcher->subscribe($eventName, fn ($event) => $this->webhookDispatcher()->handleEvent($event));
        }
    }

    private function registerTranslations(TranslationRegistry $translations): void
    {
        foreach (['en', 'fr', 'de', 'es'] as $language) {
            $path = __DIR__."/resources/translations/{$language}/messages.json";

            if (!is_file($path)) {
                continue;
            }

            $strings = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);
            $translations->register('KontorAPI', $language, $strings);
        }
    }

    /**
     * The real `/api/kontor/v1/` HTTP entry point. Only this thin
     * translation layer touches superglobals/`$this->wire()` — everything
     * it delegates to (`ApiRequestHandler`) is plain-DTO and fully
     * unit-tested. This method itself can only be lint-checked in this
     * sandbox, the same disclosed limitation as every other
     * ProcessWire-specific hook glue in this monorepo (no live
     * ProcessWire/HTTP server to exercise it against).
     */
    public function hookApiRequest(HookEvent $event): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $path = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '');

        if (!str_starts_with(trim($path, '/'), 'api/kontor/v1')) {
            return;
        }

        parse_str((string) (parse_url($requestUri, PHP_URL_QUERY) ?? ''), $queryParams);

        $headers = [];
        foreach (function_exists('getallheaders') ? (getallheaders() ?: []) : [] as $name => $value) {
            $headers[strtolower((string) $name)] = (string) $value;
        }

        $request = new ApiHttpRequest(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            path: $path,
            queryParams: $queryParams,
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

    public function tokenRepository(): ApiTokenRepository
    {
        return $this->tokenRepository ??= new ApiTokenRepository($this->pdo(), $this->organizations());
    }

    public function webhookSubscriptionRepository(): WebhookSubscriptionRepository
    {
        return $this->webhookSubscriptionRepository ??= new WebhookSubscriptionRepository($this->pdo(), $this->organizations());
    }

    public function webhookDeliveryRepository(): WebhookDeliveryRepository
    {
        return $this->webhookDeliveryRepository ??= new WebhookDeliveryRepository($this->pdo(), $this->organizations());
    }

    public function idempotencyKeyRepository(): IdempotencyKeyRepository
    {
        return $this->idempotencyKeyRepository ??= new IdempotencyKeyRepository($this->pdo(), $this->organizations());
    }

    public function resourceRegistry(): ApiResourceRegistry
    {
        return $this->resourceRegistry ??= new ApiResourceRegistry();
    }

    public function authenticator(): TokenAuthenticator
    {
        return $this->authenticator ??= new TokenAuthenticator($this->tokenRepository());
    }

    public function webhookDeliveryService(): WebhookDeliveryService
    {
        return $this->webhookDeliveryService ??= new WebhookDeliveryService(
            new CurlHttpClient(),
            $this->webhookDeliveryRepository(),
            $this->webhookSubscriptionRepository(),
        );
    }

    public function webhookDispatcher(): WebhookDispatcher
    {
        return $this->webhookDispatcher ??= new WebhookDispatcher(
            $this->webhookSubscriptionRepository(),
            $this->webhookDeliveryRepository(),
            $this->webhookDeliveryService(),
        );
    }

    public function openApiGenerator(): OpenApiGenerator
    {
        return new OpenApiGenerator($this->resourceRegistry());
    }

    public function requestHandler(): ApiRequestHandler
    {
        return $this->requestHandler ??= new ApiRequestHandler(
            new ApiRouter(),
            $this->resourceRegistry(),
            new RequestQueryParser(),
            new ApiFieldProjector(),
            new ApiResponseFactory(),
            $this->authenticator(),
            new IdempotencyService($this->idempotencyKeyRepository()),
        );
    }

    public function healthCheck(): ApiHealthCheck
    {
        return new ApiHealthCheck($this->pdo());
    }

    private function organizations(): OrganizationRepository
    {
        /** @var Kontor $kontor */
        $kontor = $this->wire()->modules->get('Kontor');

        return $kontor->container()->get(OrganizationRepository::class);
    }

    private function pdo(): \PDO
    {
        return $this->wire()->database->pdo();
    }

    public function ___install(): void
    {
        $pdo = $this->pdo();

        $runner = new MigrationRunner($pdo);
        $runner->ensureLedgerExists();
        $runner->run([
            new Migration0001CreateApiTokensTable(),
            new Migration0002CreateWebhookSubscriptionsTable(),
            new Migration0003CreateWebhookDeliveriesTable(),
            new Migration0004CreateIdempotencyKeysTable(),
        ]);

        $components = new ComponentRegistry($pdo);
        $components->markInstalled('api', self::getModuleInfo()['version'], 'api');
        $components->enable('api');
    }

    public function ___upgrade($fromVersion, $toVersion): void
    {
        $components = new ComponentRegistry($this->pdo());
        $components->markInstalled('api', self::getModuleInfo()['version'], 'api');
        $components->enable('api');
    }

    /**
     * Codex rule #10: ordinary uninstall must not remove user data.
     */
    public function ___uninstall(): void
    {
        $this->message($this->_('Kontor API module removed. Tokens, webhook subscriptions and delivery logs were kept intact.'));
    }
}
