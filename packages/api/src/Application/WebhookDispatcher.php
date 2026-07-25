<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\Domain\WebhookDelivery;
use Kontor\API\Infrastructure\Persistence\WebhookDeliveryRepository;
use Kontor\API\Infrastructure\Persistence\WebhookSubscriptionRepository;
use Kontor\SDK\Events\KontorEvent;

/**
 * Bridges Core's real event bus to webhook delivery — subscribed by
 * `KontorAPI::init()` onto `Kontor\Core\Infrastructure\Events\EventDispatcher`
 * for every distinct active subscription's `event_pattern`, the same
 * "distinct triggers" approach `kontor/automation`'s `AutomationEngine`
 * already uses for its own rules (kontor.md#9.3's dispatcher has no
 * wildcard/glob subscription of its own).
 *
 * Delivery happens synchronously, inline with the event dispatch that
 * triggered it — Core's dispatcher is itself fully synchronous
 * (kontor.md#9.3), so a slow or unreachable subscriber URL delays whatever
 * action published the event. Routing delivery through `kontor/queue`
 * instead would make it async, but that's a deliberately deferred
 * enhancement, not a requirement of this substage's "webhooks" milestone
 * (kontor.md#20.11 lists retries/backoff/log/replay, not "async").
 */
final class WebhookDispatcher
{
    public function __construct(
        private readonly WebhookSubscriptionRepository $subscriptions,
        private readonly WebhookDeliveryRepository $deliveries,
        private readonly WebhookDeliveryService $deliveryService,
    ) {
    }

    public function handleEvent(KontorEvent $event): void
    {
        foreach ($this->subscriptions->activeForEventPattern($event->event) as $subscription) {
            $delivery = WebhookDelivery::create(
                organizationId: $subscription->organizationId,
                subscriptionUid: $subscription->uid->toString(),
                eventUid: $event->eventId->toString(),
                eventName: $event->event,
                payload: $event->toArray(),
            );

            $this->deliveries->save($delivery);
            $this->deliveryService->attempt($delivery, $subscription);
        }
    }
}
