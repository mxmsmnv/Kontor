<?php

/** @var string $schema */
/** @var array<string, \Kontor\GraphQL\DTO\GraphQLObjectType> $types */
/** @var array{status: int, body: array<string, mixed>, query: string}|null $result */
/** @var string $sampleQuery */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$fieldCount = array_sum(array_map(
    static fn (\Kontor\GraphQL\DTO\GraphQLObjectType $type): int => count($type->fields),
    $types,
));
$humanize = static function (string $value): string {
    $value = preg_replace('/(?<!^)[A-Z]/', ' $0', $value) ?? $value;
    $value = str_replace(['_', '-'], ' ', $value);

    return ucfirst(strtolower(trim($value)));
};
$hasErrors = $result !== null && isset($result['body']['errors']) && $result['body']['errors'] !== [];
$resultOk = $result !== null && $result['status'] >= 200 && $result['status'] < 300 && !$hasErrors;
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Integrations · GraphQL</p>
      <h2>GraphQL explorer</h2>
      <p>Discover the business data available to integrations and safely test authenticated read queries.</p>
    </div>
    <div class="pw-module-actions kontor-pagehead__actions">
      <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>api/"><i class="fa fa-key"></i> Manage API access</a>
    </div>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-cubes"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($types)) ?></strong><span class="kontor-stat__label">Available data collections</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-list-alt"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $fieldCount) ?></strong><span class="kontor-stat__label">Queryable fields</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-eye"></i></span><span><strong class="kontor-stat__value">Read only</strong><span class="kontor-stat__label">No records are changed</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-shield"></i></span><span><strong class="kontor-stat__value">Protected</strong><span class="kontor-stat__label">Token and query limits required</span></span></div></div>
  </div>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert>
    <h3 class="uk-h4"><i class="fa fa-info-circle"></i> About this workspace</h3>
    <p>Use the explorer to confirm what an external application can read before building an integration. Access follows the API token's organization and permissions, and every request is checked before it runs.</p>
  </div>

  <?php if ($result !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-bottom" data-testid="graphql-result">
      <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
        <div class="uk-width-expand@m">
          <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Latest query</p>
          <h3 class="uk-card-title uk-margin-small-top"><?= $resultOk ? 'Query completed' : 'Query needs attention' ?></h3>
          <p class="uk-text-muted uk-margin-small-top"><?= $resultOk ? 'The API accepted the request. Review the returned data below.' : 'The request was not completed successfully. Review the response, adjust the query or token, and try again.' ?></p>
        </div>
        <div><span class="uk-label<?= $resultOk ? ' uk-label-success' : ' uk-label-warning' ?>"><?= $resultOk ? 'Success' : 'Review required' ?></span></div>
      </div>
      <pre class="uk-margin" data-testid="graphql-response"><code><?= $e(json_encode($result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></code></pre>
      <details>
        <summary class="uk-button uk-button-default uk-button-small">Request details</summary>
        <div class="uk-margin-small-top uk-text-meta">Response status <?= $e((string) $result['status']) ?></div>
      </details>
    </section>
  <?php endif; ?>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-5@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Available data</p>
        <h3 class="uk-card-title uk-margin-small-top">Choose a collection</h3>
        <p class="uk-text-muted">Each collection appears only when its component is installed and has registered a readable API resource.</p>

        <?php if ($types !== []): ?>
          <ul class="uk-list uk-list-divider uk-margin-medium-top">
            <?php foreach ($types as $resourceKey => $type): ?>
              <li>
                <div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid>
                  <div class="uk-width-expand">
                    <strong><?= $e($humanize($resourceKey)) ?></strong>
                    <div class="uk-text-meta uk-margin-small-top"><?= $e((string) count($type->fields)) ?> field<?= count($type->fields) === 1 ? '' : 's' ?> available</div>
                  </div>
                  <div><span class="uk-label">Ready</span></div>
                </div>
                <details class="uk-margin-small-top">
                  <summary>Show available fields</summary>
                  <div class="uk-margin-small-top">
                    <?php foreach (array_keys($type->fields) as $field): ?><span class="uk-label uk-margin-small-right uk-margin-small-bottom"><?= $e($humanize($field)) ?></span><?php endforeach; ?>
                  </div>
                </details>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="uk-placeholder uk-text-center">
            <i class="fa fa-plug fa-2x uk-text-muted"></i>
            <h4>No data collections available</h4>
            <p class="uk-text-muted">Install and enable a Kontor component that exposes API data, then return here.</p>
            <a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>components/">Open Components</a>
          </div>
        <?php endif; ?>

        <details class="uk-margin-medium-top">
          <summary class="uk-button uk-button-default uk-button-small"><i class="fa fa-code"></i> Developer reference</summary>
          <div class="uk-margin-medium-top">
            <p class="uk-text-muted">Technical names and required token access for integration development.</p>
            <ul class="uk-list uk-list-divider">
              <?php foreach ($types as $resourceKey => $type): ?><li><strong><?= $e($type->name) ?></strong><div class="uk-text-meta"><?= $e($resourceKey) ?> · <?= $e($resourceKey) ?>:read</div></li><?php endforeach; ?>
            </ul>
            <details class="uk-margin-top">
              <summary>View schema definition</summary>
              <pre class="uk-margin-small-top"><code><?= $e($schema) ?></code></pre>
            </details>
          </div>
        </details>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-3-5@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
          <div class="uk-width-expand@m">
            <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Authenticated test</p>
            <h3 class="uk-card-title uk-margin-small-top">Run a read query</h3>
            <p class="uk-text-muted uk-margin-small-top">Paste an active API token, adapt the example query and inspect the response. The token is used for this request only and is not shown again.</p>
          </div>
          <div><span class="uk-label">Limit 1,000</span></div>
        </div>

        <form class="uk-form-stacked uk-margin-medium-top" method="post" action="<?= $e($adminUrl) ?>graphql-run/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">

          <div class="uk-margin">
            <label class="uk-form-label" for="graphql-token">API token</label>
            <div class="uk-inline uk-width-1-1 uk-margin-small-top">
              <span class="uk-form-icon"><i class="fa fa-key"></i></span>
              <input class="uk-input" id="graphql-token" name="token" type="password" autocomplete="off" placeholder="Paste a token with read access" aria-describedby="graphql-token-help" required>
            </div>
            <div class="uk-text-meta uk-margin-small-top" id="graphql-token-help">Create or revoke tokens in API access. Treat tokens like passwords and share only the minimum required access.</div>
          </div>

          <div class="uk-margin">
            <label class="uk-form-label" for="graphql-query">Query</label>
            <textarea class="uk-textarea uk-margin-small-top" id="graphql-query" name="query" rows="14" spellcheck="false" aria-describedby="graphql-query-help" required><?= $e($result['query'] ?? $sampleQuery) ?></textarea>
            <div class="uk-text-meta uk-margin-small-top" id="graphql-query-help">Request only the fields the integration needs. Large or deeply nested queries are rejected by the complexity limit.</div>
          </div>

          <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-medium-top" uk-grid>
            <div class="uk-text-meta"><i class="fa fa-lock"></i> Authenticated · organization-scoped · read only</div>
            <div><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-play"></i> Run query</button></div>
          </div>
        </form>
      </section>
    </div>
  </div>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
    <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Recommended workflow</p>
    <h3 class="uk-card-title uk-margin-small-top">From access to integration</h3>
    <div class="uk-grid-medium uk-child-width-1-1 uk-child-width-1-3@m uk-margin-top" uk-grid>
      <div><strong><span class="uk-label uk-margin-small-right">1</span> Create least-privilege access</strong><p class="uk-text-muted uk-margin-small-top">Issue a token with read access only for the collections the integration needs.</p></div>
      <div><strong><span class="uk-label uk-margin-small-right">2</span> Test the smallest query</strong><p class="uk-text-muted uk-margin-small-top">Start with identifiers and names, then add fields one at a time.</p></div>
      <div><strong><span class="uk-label uk-margin-small-right">3</span> Move into your application</strong><p class="uk-text-muted uk-margin-small-top">Use the tested query in the client and keep token rotation outside source code.</p></div>
    </div>
  </section>
</div>
