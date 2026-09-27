<?php

declare(strict_types=1);

namespace Kontor\API\Application;

use Kontor\API\Contracts\HttpClientInterface;
use Kontor\API\Domain\WebhookDelivery;
use Kontor\API\Domain\WebhookSubscription;
use Kontor\API\Infrastructure\Persistence\WebhookDeliveryRepository;
use Kontor\API\Infrastructure\Persistence\WebhookSubscriptionRepository;

/**
 * The "webhooks" milestone (kontor.md#20.11): signature, retries,
 * exponential backoff, delivery log, disable after repeated permanent
 * failures, replay.
 */
final class WebhookDeliveryService
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly WebhookDeliveryRepository $deliveries,
        private readonly WebhookSubscriptionRepository $subscriptions,
        private readonly WebhookBackoffCalculator $backoff = new WebhookBackoffCalculator(),
    ) {
    }

    public function attempt(WebhookDelivery $delivery, WebhookSubscription $subscription): void
    {
        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, $subscription->secret);

        $headers = [
            'Content-Type' => 'application/json',
            'X-Kontor-Event' => $delivery->eventName,
            'X-Kontor-Delivery' => $delivery->uid->toString(),
            'X-Kontor-Signature' => "sha256={$signature}",
        ];

        try {
            $response = $this->http->post($subscription->url, $body, $headers);
        } catch (\Throwable $e) {
            $this->recordFailure($delivery, $subscription, null, $e->getMessage());

            return;
        }

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $delivery->markDelivered($response['status']);
            $subscription->recordSuccess();
            $this->deliveries->save($delivery);
            $this->subscriptions->save($subscription);

            return;
        }

        $this->recordFailure($delivery, $subscription, $response['status'], "HTTP {$response['status']}");
    }

    /**
     * kontor.md#20.11 "replay": forces one more delivery attempt right
     * now, regardless of the delivery's current status or scheduled
     * `next_attempt_at`.
     */
    public function replay(string $deliveryUid): void
    {
        $delivery = $this->deliveries->require($deliveryUid);
        $subscription = $this->subscriptions->require($delivery->subscriptionUid);

        $delivery->resetForReplay();
        $this->attempt($delivery, $subscription);
    }

    private function recordFailure(WebhookDelivery $delivery, WebhookSubscription $subscription, ?int $responseCode, string $error): void
    {
        $nextAttemptAt = $this->backoff->nextAttemptAt($delivery->attemptCount);

        $delivery->markAttemptFailed($responseCode, $error, $nextAttemptAt);
        $subscription->recordFailure();

        if ($this->backoff->shouldDisable($subscription->consecutiveFailures)) {
            $subscription->disable();
        }

        $this->deliveries->save($delivery);
        $this->subscriptions->save($subscription);
    }
}
