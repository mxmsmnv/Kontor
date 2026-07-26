<?php

declare(strict_types=1);

namespace Kontor\API\Tests\Integration;

use Kontor\API\Application\WebhookDeliveryService;
use Kontor\API\Contracts\HttpClientInterface;
use Kontor\API\Domain\WebhookDelivery;
use Kontor\API\Domain\WebhookSubscription;
use Kontor\API\Infrastructure\Persistence\WebhookDeliveryRepository;
use Kontor\API\Infrastructure\Persistence\WebhookSubscriptionRepository;
use Kontor\API\Migrations\Migration0002CreateWebhookSubscriptionsTable;
use Kontor\API\Migrations\Migration0003CreateWebhookDeliveriesTable;
use Kontor\Core\Infrastructure\Persistence\OrganizationRepository;
use Kontor\Core\Migrations\Migration0001CreateOrganizationsTable;
use Kontor\Core\Testing\DatabaseTestCase;

/**
 * The first real consumer of the shared `Kontor\Core\Testing\DatabaseTestCase`
 * (kontor.md Substage 7.4) outside `kontor/core` itself. Uses a fake
 * `HttpClientInterface` since this sandbox has no outbound network access
 * to verify webhook delivery against — see that interface's own doc
 * comment.
 */
final class WebhookDeliveryServiceTest extends DatabaseTestCase
{
    protected function migrations(): array
    {
        return [
            new Migration0001CreateOrganizationsTable(),
            new Migration0002CreateWebhookSubscriptionsTable(),
            new Migration0003CreateWebhookDeliveriesTable(),
        ];
    }

    protected function tablesToDrop(): array
    {
        return ['kontor_webhook_deliveries', 'kontor_webhook_subscriptions', 'kontor_organizations', 'kontor_migrations'];
    }

    public function test_a_successful_delivery_marks_delivered_and_resets_failures(): void
    {
        $subscriptions = new WebhookSubscriptionRepository($this->pdo, new OrganizationRepository($this->pdo));
        $deliveries = new WebhookDeliveryRepository($this->pdo, new OrganizationRepository($this->pdo));

        $subscription = WebhookSubscription::create($this->organizationUid, 'https://example.com/hook', 'invoice.issued', 'shh-secret');
        $subscription->recordFailure();
        $subscriptions->save($subscription);

        $delivery = WebhookDelivery::create($this->organizationUid, $subscription->uid->toString(), 'evt_1', 'invoice.issued', ['foo' => 'bar']);
        $deliveries->save($delivery);

        $http = new class implements HttpClientInterface {
            public ?string $signatureReceived = null;

            public function post(string $url, string $body, array $headers): array
            {
                $this->signatureReceived = $headers['X-Kontor-Signature'];

                return ['status' => 200, 'body' => 'ok'];
            }
        };

        $service = new WebhookDeliveryService($http, $deliveries, $subscriptions);
        $service->attempt($delivery, $subscription);

        $this->assertSame('delivered', $delivery->status);
        $this->assertSame(0, $subscription->consecutiveFailures);
        $this->assertSame('sha256='.hash_hmac('sha256', json_encode(['foo' => 'bar'], JSON_THROW_ON_ERROR), 'shh-secret'), $http->signatureReceived);

        $reloaded = $deliveries->require($delivery->uid->toString());
        $this->assertSame('delivered', $reloaded->status);
    }

    public function test_a_failed_delivery_schedules_a_retry_and_increments_failures(): void
    {
        $subscriptions = new WebhookSubscriptionRepository($this->pdo, new OrganizationRepository($this->pdo));
        $deliveries = new WebhookDeliveryRepository($this->pdo, new OrganizationRepository($this->pdo));

        $subscription = WebhookSubscription::create($this->organizationUid, 'https://example.com/hook', 'invoice.issued', 'shh-secret');
        $subscriptions->save($subscription);

        $delivery = WebhookDelivery::create($this->organizationUid, $subscription->uid->toString(), 'evt_1', 'invoice.issued', []);
        $deliveries->save($delivery);

        $http = new class implements HttpClientInterface {
            public function post(string $url, string $body, array $headers): array
            {
                return ['status' => 500, 'body' => 'server error'];
            }
        };

        $service = new WebhookDeliveryService($http, $deliveries, $subscriptions);
        $service->attempt($delivery, $subscription);

        $this->assertSame('pending', $delivery->status);
        $this->assertSame(1, $delivery->attemptCount);
        $this->assertSame(1, $subscription->consecutiveFailures);
        $this->assertNotNull($delivery->nextAttemptAt);
    }

    public function test_repeated_failures_disable_the_subscription(): void
    {
        $subscriptions = new WebhookSubscriptionRepository($this->pdo, new OrganizationRepository($this->pdo));
        $deliveries = new WebhookDeliveryRepository($this->pdo, new OrganizationRepository($this->pdo));

        $subscription = WebhookSubscription::create($this->organizationUid, 'https://example.com/hook', 'invoice.issued', 'shh-secret');
        $subscriptions->save($subscription);

        $http = new class implements HttpClientInterface {
            public function post(string $url, string $body, array $headers): array
            {
                return ['status' => 500, 'body' => 'server error'];
            }
        };

        $service = new WebhookDeliveryService($http, $deliveries, $subscriptions);

        for ($i = 0; $i < 10; $i++) {
            $delivery = WebhookDelivery::create($this->organizationUid, $subscription->uid->toString(), "evt_{$i}", 'invoice.issued', []);
            $deliveries->save($delivery);
            $service->attempt($delivery, $subscription);
        }

        $this->assertSame('disabled', $subscription->status);
        $this->assertFalse($subscription->isActive());
    }

    public function test_replay_forces_another_attempt_and_can_succeed(): void
    {
        $subscriptions = new WebhookSubscriptionRepository($this->pdo, new OrganizationRepository($this->pdo));
        $deliveries = new WebhookDeliveryRepository($this->pdo, new OrganizationRepository($this->pdo));

        $subscription = WebhookSubscription::create($this->organizationUid, 'https://example.com/hook', 'invoice.issued', 'shh-secret');
        $subscriptions->save($subscription);

        $delivery = WebhookDelivery::create($this->organizationUid, $subscription->uid->toString(), 'evt_1', 'invoice.issued', []);
        $deliveries->save($delivery);

        $http = new class implements HttpClientInterface {
            public int $calls = 0;

            public function post(string $url, string $body, array $headers): array
            {
                $this->calls++;

                return ['status' => 200, 'body' => 'ok'];
            }
        };

        $service = new WebhookDeliveryService($http, $deliveries, $subscriptions);
        $service->replay($delivery->uid->toString());

        $this->assertSame(1, $http->calls);
        $this->assertSame('delivered', $deliveries->require($delivery->uid->toString())->status);
    }

    public function test_archived_subscriptions_are_omitted_from_organization_listing(): void
    {
        $subscriptions = new WebhookSubscriptionRepository($this->pdo, new OrganizationRepository($this->pdo));
        $subscription = WebhookSubscription::create(
            $this->organizationUid,
            'https://example.com/hook',
            'invoice.issued',
            'shh-secret',
        );
        $subscriptions->save($subscription);

        $this->assertCount(1, $subscriptions->forOrganization($this->organizationUid));

        $subscriptions->archive($subscription->uid->toString());

        $this->assertSame([], $subscriptions->forOrganization($this->organizationUid));
    }
}
