<?php

/** @var \Kontor\API\Domain\ApiToken[] $tokens */
/** @var \Kontor\API\Domain\WebhookSubscription[] $subscriptions */
/** @var \Kontor\API\Domain\WebhookDelivery[] $deliveries */
/** @var array<string, \Kontor\API\Contracts\ApiResourceInterface> $resources */
/** @var array<string, mixed> $openApi */
/** @var array{kind: string, label: string, value: string}|null $oneTimeSecret */
/** @var bool $canManageWebhooks */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$humanize = static function (string $value): string {
    $value = preg_replace('/(?<!^)[A-Z]/', ' $0', $value) ?? $value;
    $value = str_replace(['_', '-', '.'], ' ', $value);

    return ucfirst(strtolower(trim($value)));
};
$scopeLabel = static function (string $scope) use ($humanize): string {
    [$resource, $access] = array_pad(explode(':', $scope, 2), 2, '');

    return $humanize($resource) . ($access !== '' ? ' · ' . $humanize($access) : '');
};
$activeTokens = count(array_filter(
    $tokens,
    static fn (\Kontor\API\Domain\ApiToken $token): bool => $token->isActive() && !$token->isExpired(),
));
$activeSubscriptions = count(array_filter(
    $subscriptions,
    static fn (\Kontor\API\Domain\WebhookSubscription $subscription): bool => $subscription->isActive(),
));
$deliveredCount = count(array_filter(
    $deliveries,
    static fn (\Kontor\API\Domain\WebhookDelivery $delivery): bool => $delivery->status === 'delivered',
));
$failedCount = count(array_filter(
    $deliveries,
    static fn (\Kontor\API\Domain\WebhookDelivery $delivery): bool => $delivery->status === 'exhausted',
));
$scopeOptions = [];
foreach ($resources as $resourceKey => $resource) {
    $schema = $resource->schema();
    $scopeOptions[$resourceKey . ':read'] = $humanize($resourceKey) . ' · Read';
    if ($schema->supportsCreate || $schema->supportsUpdate || $schema->supportsDelete) {
        $scopeOptions[$resourceKey . ':write'] = $humanize($resourceKey) . ' · Write';
    }
}
$deliveryStateClass = static fn (string $status): string => match ($status) {
    'delivered' => ' uk-label-success',
    'exhausted' => ' uk-label-danger',
    default => ' uk-label-warning',
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Integrations · REST API</p>
      <h2>API access</h2>
      <p>Connect trusted applications to Kontor with controlled data access and signed event notifications.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>graphql/"><i class="fa fa-share-alt"></i> GraphQL explorer</a>
    </div>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $activeTokens > 0 ? ' kontor-stat__icon--success' : '' ?>"><i class="fa fa-key"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $activeTokens) ?></strong><span class="kontor-stat__label">Active access tokens</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-database"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($resources)) ?></strong><span class="kontor-stat__label">Available data collections</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $activeSubscriptions > 0 ? ' kontor-stat__icon--success' : '' ?>"><i class="fa fa-bell"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $activeSubscriptions) ?></strong><span class="kontor-stat__label">Active event destinations</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $failedCount > 0 ? ' kontor-stat__icon--danger' : ($deliveredCount > 0 ? ' kontor-stat__icon--success' : '') ?>"><i class="fa fa-paper-plane"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $deliveredCount) ?></strong><span class="kontor-stat__label"><?= $failedCount > 0 ? $e((string) $failedCount) . ' delivery failures' : 'Recent deliveries completed' ?></span></span></div></div>
  </div>

  <?php if ($oneTimeSecret !== null): ?>
    <section class="uk-alert-warning uk-margin-medium-bottom" uk-alert data-testid="one-time-secret-panel">
      <h3 class="uk-h4"><i class="fa fa-exclamation-triangle"></i> Save this <?= $e($oneTimeSecret['kind']) ?> secret now</h3>
      <p>The secret for <strong><?= $e($oneTimeSecret['label']) ?></strong> is shown once. Copy it to the application's secure configuration before leaving or refreshing this page.</p>
      <div class="uk-grid-small uk-flex-middle" uk-grid>
        <div class="uk-width-expand"><input class="uk-input" id="kontor-api-one-time-secret" data-testid="one-time-secret" value="<?= $e($oneTimeSecret['value']) ?>" readonly aria-label="One-time secret"></div>
        <div><button class="uk-button uk-button-primary" type="button" data-kontor-copy-target="#kontor-api-one-time-secret"><i class="fa fa-copy"></i> Copy secret</button></div>
      </div>
    </section>
  <?php endif; ?>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert>
    <h3 class="uk-h4"><i class="fa fa-info-circle"></i> About this workspace</h3>
    <p>Tokens let an application request business data; webhooks notify an application when something happens. Use a separate token or destination for each integration so access can be reviewed and removed without disrupting others.</p>
  </div>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-3-5@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
          <div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Application access</p><h3 class="uk-card-title uk-margin-small-top">Access tokens</h3><p class="uk-text-muted uk-margin-small-top">Issue a dedicated credential for each application and revoke it as soon as access is no longer needed.</p></div>
          <div><span class="uk-label"><?= $e((string) $activeTokens) ?> active</span></div>
        </div>

        <details<?= $tokens === [] ? ' open' : '' ?> class="uk-margin-medium-top">
          <summary class="uk-button uk-button-primary"><i class="fa fa-plus"></i> Issue a token</summary>
          <form class="uk-form-stacked uk-margin-medium-top" method="post" action="<?= $e($adminUrl) ?>api-token/" data-kontor-api-token-form>
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <div class="uk-grid-small" uk-grid>
              <div class="uk-width-1-1 uk-width-1-2@m">
                <label class="uk-form-label" for="api-token-name">Application name</label>
                <input class="uk-input uk-margin-small-top" id="api-token-name" name="name" maxlength="255" placeholder="Reporting dashboard" aria-describedby="api-token-name-help" required>
                <div class="uk-text-meta uk-margin-small-top" id="api-token-name-help">Use a name teammates will recognize when reviewing access later.</div>
              </div>
              <div class="uk-width-1-1 uk-width-1-2@m">
                <label class="uk-form-label" for="api-token-expiry">Expires</label>
                <input class="uk-input uk-margin-small-top" id="api-token-expiry" name="expires_at" type="datetime-local" aria-describedby="api-token-expiry-help">
                <div class="uk-text-meta uk-margin-small-top" id="api-token-expiry-help">Optional. Prefer an expiry date for temporary or third-party access.</div>
              </div>
            </div>

            <fieldset class="uk-fieldset uk-margin-medium-top">
              <legend class="uk-legend">Data permissions</legend>
              <p class="uk-text-muted">Start with restricted access and select only what the application needs.</p>
              <label><input class="uk-radio" type="radio" name="access_mode" value="restricted" checked> Restricted access</label>
              <label class="uk-margin-left"><input class="uk-radio" type="radio" name="access_mode" value="unrestricted"> Full access</label>
              <?php if ($scopeOptions !== []): ?>
                <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-margin-top" uk-grid>
                  <?php foreach ($scopeOptions as $scope => $label): ?><label><input class="uk-checkbox" type="checkbox" name="scopes[]" value="<?= $e($scope) ?>" data-kontor-api-scope> <?= $e($label) ?></label><?php endforeach; ?>
                </div>
              <?php else: ?><p class="uk-text-muted">No component data is currently available. Enable an API-capable component before issuing restricted access.</p><?php endif; ?>
              <div class="uk-text-meta uk-margin-small-top">Write access can create or change records when the selected component supports it. Full access also covers resources installed later.</div>
            </fieldset>

            <div class="uk-flex uk-flex-right uk-margin-medium-top"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-key"></i> Create token</button></div>
          </form>
        </details>

        <?php if ($tokens !== []): ?>
          <ul class="uk-list uk-list-divider uk-margin-medium-top">
            <?php foreach ($tokens as $token): ?>
              <?php $tokenActive = $token->isActive() && !$token->isExpired(); ?>
              <li>
                <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
                  <div class="uk-width-expand@m">
                    <strong><?= $e($token->name) ?></strong>
                    <div class="uk-text-meta uk-margin-small-top">
                      <?= $token->lastUsedAt !== null ? 'Last used ' . $e($token->lastUsedAt->format('M j, Y · H:i')) : 'Not used yet' ?>
                      · <?= $token->expiresAt !== null ? 'Expires ' . $e($token->expiresAt->format('M j, Y · H:i')) : 'No expiry' ?>
                    </div>
                  </div>
                  <div><span class="uk-label<?= $tokenActive ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $tokenActive ? 'Active' : ($token->isExpired() ? 'Expired' : $e($humanize($token->status))) ?></span></div>
                </div>
                <div class="uk-margin-small-top">
                  <?php if ($token->scopes === []): ?><span class="uk-label uk-label-warning">Full access</span><?php else: ?><?php foreach ($token->scopes as $scope): ?><span class="uk-label uk-margin-small-right uk-margin-small-bottom"><?= $e($scopeLabel($scope)) ?></span><?php endforeach; ?><?php endif; ?>
                </div>
                <?php if ($token->isActive()): ?><form class="uk-margin-small-top" method="post" action="<?= $e($adminUrl) ?>api-token-revoke/" data-kontor-confirm="Revoke this token? The connected application will lose access immediately."><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="token_uid" value="<?= $e($token->uid->toString()) ?>"><button class="uk-button uk-button-default uk-button-small" type="submit"><i class="fa fa-ban"></i> Revoke access</button></form><?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-key fa-2x uk-text-muted"></i><h4>No applications connected</h4><p class="uk-text-muted">Issue the first token when an application is ready to connect.</p></div>
        <?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-2-5@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connected components</p>
        <h3 class="uk-card-title uk-margin-small-top">Available data</h3>
        <p class="uk-text-muted">This list updates automatically as API-capable Kontor components are installed or removed.</p>
        <?php if ($resources !== []): ?>
          <ul class="uk-list uk-list-divider uk-margin-medium-top">
            <?php foreach ($resources as $resourceKey => $resource): ?>
              <?php $resourceSchema = $resource->schema(); ?>
              <li>
                <div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid>
                  <div class="uk-width-expand"><strong><?= $e($humanize($resourceKey)) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e((string) count($resourceSchema->fields)) ?> fields available</div></div>
                  <div><span class="uk-label">Read</span><?php if ($resourceSchema->supportsCreate || $resourceSchema->supportsUpdate || $resourceSchema->supportsDelete): ?> <span class="uk-label">Write</span><?php endif; ?></div>
                </div>
                <details class="uk-margin-small-top"><summary>View capabilities</summary><div class="uk-margin-small-top"><strong>Actions:</strong> View lists and records<?= $resourceSchema->supportsCreate ? ', create' : '' ?><?= $resourceSchema->supportsUpdate ? ', update' : '' ?><?= $resourceSchema->supportsDelete ? ', delete' : '' ?>.<br><span class="uk-text-meta">Fields: <?= $e(implode(', ', array_map($humanize, array_keys($resourceSchema->fields)))) ?></span></div></details>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?><div class="uk-placeholder uk-text-center"><p class="uk-text-muted">No API-capable component data is available.</p><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open Components</a></div><?php endif; ?>

        <details class="uk-margin-medium-top">
          <summary class="uk-button uk-button-default uk-button-small"><i class="fa fa-code"></i> Developer reference</summary>
          <div class="uk-margin-medium-top">
            <p><strong>Base URL</strong><br><code>/api/kontor/v1/</code></p>
            <p class="uk-text-muted">Send the token as an Authorization Bearer header. Collection and record routes follow the operations shown above.</p>
            <details class="uk-margin-top"><summary>View OpenAPI document</summary><pre class="uk-margin-small-top"><code><?= $e(json_encode($openApi, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre></details>
          </div>
        </details>
      </section>
    </div>
  </div>

  <?php if ($canManageWebhooks): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
        <div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Event notifications</p><h3 class="uk-card-title uk-margin-small-top">Webhook destinations</h3><p class="uk-text-muted uk-margin-small-top">Send signed notifications to another application when a selected Kontor event occurs.</p></div>
        <div><span class="uk-label"><?= $e((string) $activeSubscriptions) ?> active</span></div>
      </div>

      <details<?= $subscriptions === [] ? ' open' : '' ?> class="uk-margin-medium-top">
        <summary class="uk-button uk-button-primary"><i class="fa fa-plus"></i> Add a destination</summary>
        <form class="uk-form-stacked uk-margin-medium-top" method="post" action="<?= $e($adminUrl) ?>api-webhook/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <div class="uk-grid-small" uk-grid>
            <div class="uk-width-1-1 uk-width-2-3@m"><label class="uk-form-label" for="api-webhook-url">Destination URL</label><input class="uk-input uk-margin-small-top" id="api-webhook-url" name="url" type="url" maxlength="2048" placeholder="https://app.example.com/webhooks/kontor" aria-describedby="api-webhook-url-help" required><div class="uk-text-meta uk-margin-small-top" id="api-webhook-url-help">Use an HTTPS endpoint that can receive signed POST requests from this server.</div></div>
            <div class="uk-width-1-1 uk-width-1-3@m"><label class="uk-form-label" for="api-webhook-event">Event</label><input class="uk-input uk-margin-small-top" id="api-webhook-event" name="event_pattern" maxlength="150" pattern="[a-z][a-z0-9._-]{1,149}" placeholder="invoice.issued" aria-describedby="api-webhook-event-help" required><div class="uk-text-meta uk-margin-small-top" id="api-webhook-event-help">Enter the exact event name supplied by the connected component.</div></div>
          </div>
          <div class="uk-flex uk-flex-right uk-margin-medium-top"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-bell"></i> Create destination</button></div>
        </form>
      </details>

      <?php if ($subscriptions !== []): ?>
        <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@l uk-margin-medium-top" uk-grid>
          <?php foreach ($subscriptions as $subscription): ?>
            <?php $host = (string) (parse_url($subscription->url, PHP_URL_HOST) ?: $subscription->url); ?>
            <div><div class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
              <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e($humanize($subscription->eventPattern)) ?></strong><div class="uk-text-meta uk-margin-small-top"><i class="fa fa-globe"></i> <?= $e($host) ?></div></div><div><span class="uk-label<?= $subscription->isActive() ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $subscription->isActive() ? 'Active' : $e($humanize($subscription->status)) ?></span></div></div>
              <p class="uk-text-muted uk-margin-small-top"><?= $subscription->consecutiveFailures > 0 ? $e((string) $subscription->consecutiveFailures) . ' consecutive delivery failures need attention.' : 'No consecutive delivery failures.' ?></p>
              <details><summary>Destination details</summary><div class="uk-text-meta uk-margin-small-top"><?= $e($subscription->url) ?><br>Event: <?= $e($subscription->eventPattern) ?></div></details>
              <?php if ($subscription->isActive()): ?><form class="uk-margin-top" method="post" action="<?= $e($adminUrl) ?>api-webhook-archive/" data-kontor-confirm="Archive this webhook destination? New events will no longer be sent to it."><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="subscription_uid" value="<?= $e($subscription->uid->toString()) ?>"><button class="uk-button uk-button-default uk-button-small" type="submit"><i class="fa fa-archive"></i> Archive destination</button></form><?php endif; ?>
            </div></div>
          <?php endforeach; ?>
        </div>
      <?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-bell-o fa-2x uk-text-muted"></i><h4>No event destinations</h4><p class="uk-text-muted">Add one when another application is ready to receive Kontor events.</p></div><?php endif; ?>
    </section>

    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Delivery activity</p><h3 class="uk-card-title uk-margin-small-top">Recent notifications</h3><p class="uk-text-muted uk-margin-small-top">Confirm that connected applications received events and identify destinations that need attention.</p></div><div><span class="uk-label"><?= $e((string) count($deliveries)) ?> recent</span></div></div>
      <?php if ($deliveries !== []): ?>
        <ul class="uk-list uk-list-divider uk-margin-medium-top">
          <?php foreach ($deliveries as $delivery): ?>
            <li><div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><strong><?= $e($humanize($delivery->eventName)) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($delivery->createdAt->format('M j, Y · H:i:s')) ?> · <?= $e((string) $delivery->attemptCount) ?> attempt<?= $delivery->attemptCount === 1 ? '' : 's' ?></div><?php if ($delivery->lastError !== null): ?><details class="uk-margin-small-top"><summary>Failure details</summary><div class="uk-text-meta uk-margin-small-top"><?= $e($delivery->lastError) ?></div></details><?php endif; ?></div><div><span class="uk-label<?= $deliveryStateClass($delivery->status) ?>"><?= $e($humanize($delivery->status)) ?></span><?php if ($delivery->responseCode !== null): ?> <span class="uk-text-meta">Response <?= $e((string) $delivery->responseCode) ?></span><?php endif; ?></div></div></li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-paper-plane-o fa-2x uk-text-muted"></i><h4>No notifications sent yet</h4><p class="uk-text-muted">Delivery activity will appear after a subscribed event occurs.</p></div><?php endif; ?>
    </section>
  <?php else: ?>
    <div class="uk-alert-primary uk-margin-medium-top" uk-alert><p><i class="fa fa-lock"></i> Webhook destinations are managed by workspace administrators.</p></div>
  <?php endif; ?>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
    <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Recommended workflow</p>
    <h3 class="uk-card-title uk-margin-small-top">Connect an application safely</h3>
    <div class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-3@m uk-margin-top" uk-grid>
      <div><strong><span class="uk-label uk-margin-small-right">1</span> Grant minimum access</strong><p class="uk-text-muted uk-margin-small-top">Create a dedicated, expiring token with only the required data permissions.</p></div>
      <div><strong><span class="uk-label uk-margin-small-right">2</span> Store the secret once</strong><p class="uk-text-muted uk-margin-small-top">Move the one-time value into the application's secret store immediately.</p></div>
      <div><strong><span class="uk-label uk-margin-small-right">3</span> Monitor and revoke</strong><p class="uk-text-muted uk-margin-small-top">Review usage and delivery health, then remove access when the integration retires.</p></div>
    </div>
  </section>
</div>
