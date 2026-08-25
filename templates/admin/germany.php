<?php

/** @var string $countryCode */
/** @var string[] $documentFormats */
/** @var array<int, array{definition: array{code: string, name: string, type: string}, account: \Kontor\Ledger\Domain\Account|null, compatible: bool}> $chartStatus */
/** @var array{taxId: string, valid: bool}|null $taxResult */
/** @var array{invoiceNumber: string, xml: string, bytes: int, netMinor: int, taxMinor: int, grossMinor: int}|null $xmlResult */
/** @var bool $canConfigure */
/** @var bool $canViewLedger */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$installed = count(array_filter($chartStatus, static fn (array $row): bool => $row['account'] !== null));
$missing = count($chartStatus) - $installed;
$conflicts = count(array_filter($chartStatus, static fn (array $row): bool => !$row['compatible']));
$ready = count(array_filter(
    $chartStatus,
    static fn (array $row): bool => $row['compatible'] && $row['account'] !== null && $row['account']->isActive(),
));
$money = static fn (int $minor): string => number_format($minor / 100, 2, '.', ',') . ' EUR';
$accountState = static fn (array $row): string => !$row['compatible']
    ? 'Needs review'
    : ($row['account'] === null
        ? 'Not added'
        : ($row['account']->isActive() ? 'Ready' : 'Inactive'));
$accountStateClass = static fn (array $row): string => !$row['compatible'] || ($row['account'] !== null && !$row['account']->isActive())
    ? ' uk-label-warning'
    : ($row['account'] !== null ? ' uk-label-success' : '');
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Localization · Germany</p>
      <h2>Germany</h2>
      <p>Prepare German business data with VAT ID format checks, a starter accounting map and an electronic-invoice preview.</p>
    </div>
    <?php if ($canViewLedger): ?><div class="pw-module-actions kontor-pagehead__actions"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>ledger/"><i class="fa fa-balance-scale"></i> Open Ledger</a></div><?php endif; ?>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-flag"></i></span><span><strong class="kontor-stat__value">Germany</strong><span class="kontor-stat__label">Localization region</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-check-circle-o"></i></span><span><strong class="kontor-stat__value">Available</strong><span class="kontor-stat__label">VAT ID format check</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $conflicts === 0 && $ready === count($chartStatus) ? ' kontor-stat__icon--success' : '' ?>"><i class="fa fa-book"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $ready) ?>/<?= $e((string) count($chartStatus)) ?></strong><span class="kontor-stat__label">Accounting references ready</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-file-text-o"></i></span><span><strong class="kontor-stat__value">Preview</strong><span class="kontor-stat__label">Electronic invoice output</span></span></div></div>
  </div>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert>
    <h3 class="uk-h4"><i class="fa fa-info-circle"></i> Compliance scope</h3>
    <p>These tools help prepare and review data. The VAT check verifies format and checksum only; it does not confirm registration with BZSt or VIES. The invoice output is a structural preview and is not a certified EN 16931 or XRechnung validation.</p>
  </div>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-1-2@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">VAT identification</p>
        <h3 class="uk-card-title uk-margin-small-top">Check a German VAT ID</h3>
        <p class="uk-text-muted">Use this before saving a German company tax number to catch typing and checksum errors.</p>
        <form class="uk-form-stacked" method="post" action="<?= $e($adminUrl) ?>germany-tax-id/">
          <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
          <label class="uk-form-label" for="germany-tax-id">VAT ID</label>
          <div class="uk-grid-small uk-flex-bottom uk-margin-small-top" uk-grid>
            <div class="uk-width-expand"><input class="uk-input" id="germany-tax-id" name="tax_id" value="<?= $e($taxResult['taxId'] ?? '') ?>" maxlength="32" placeholder="DE123456789" aria-describedby="germany-tax-id-help" required autocomplete="off"></div>
            <div><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-check"></i> Check</button></div>
          </div>
          <div class="uk-text-meta uk-margin-small-top" id="germany-tax-id-help">Enter DE followed by nine digits. Spaces and hyphens are accepted.</div>
        </form>
        <?php if ($taxResult !== null): ?>
          <div class="uk-alert-<?= $taxResult['valid'] ? 'success' : 'warning' ?> uk-margin" uk-alert>
            <p><strong><?= $e($taxResult['taxId']) ?></strong> — <?= $taxResult['valid'] ? 'format and checksum are valid.' : 'format or checksum is invalid.' ?></p>
          </div>
        <?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-1-2@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body uk-height-1-1">
        <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid>
          <div class="uk-width-expand"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">German accounting</p><h3 class="uk-card-title uk-margin-small-top">Starter account readiness</h3></div>
          <div><span class="uk-label<?= $conflicts > 0 ? ' uk-label-warning' : ($ready === count($chartStatus) ? ' uk-label-success' : '') ?>"><?= $conflicts > 0 ? $e((string) $conflicts) . ' to review' : ($ready === count($chartStatus) ? 'Ready' : $e((string) $missing) . ' missing') ?></span></div>
        </div>
        <p class="uk-text-muted">Kontor can add a small German starter map to Ledger. Existing accounts are preserved and checked before anything is added.</p>
        <?php if ($conflicts > 0): ?><div class="uk-alert-warning" uk-alert><p>One or more existing account codes have different names, types or currencies. Review the mapping in Ledger before adding missing accounts.</p></div><?php endif; ?>
        <?php if ($canConfigure && $missing > 0 && $conflicts === 0): ?>
          <form method="post" action="<?= $e($adminUrl) ?>germany-seed-chart/" data-kontor-confirm="Add the missing German starter accounts to Ledger?">
            <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
            <button class="uk-button uk-button-primary" type="submit"><i class="fa fa-plus"></i> Add <?= $e((string) $missing) ?> missing account<?= $missing === 1 ? '' : 's' ?></button>
          </form>
        <?php elseif ($ready === count($chartStatus)): ?><p><i class="fa fa-check-circle uk-text-success"></i> All starter references are active and compatible.</p><?php endif; ?>
        <details class="uk-margin-medium-top">
          <summary class="uk-button uk-button-default uk-button-small">Review account mapping</summary>
          <ul class="uk-list uk-list-divider uk-margin">
            <?php foreach ($chartStatus as $row): ?><li><div class="uk-flex uk-flex-between uk-flex-middle uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e($row['definition']['name']) ?></strong><div class="uk-text-meta uk-margin-small-top">Account <?= $e($row['definition']['code']) ?> · <?= $e(ucfirst($row['definition']['type'])) ?> · EUR</div></div><div><span class="uk-label<?= $accountStateClass($row) ?>"><?= $e($accountState($row)) ?></span></div></div></li><?php endforeach; ?>
          </ul>
        </details>
      </section>
    </div>
  </div>

  <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
    <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid>
      <div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Electronic invoicing</p><h3 class="uk-card-title uk-margin-small-top">Build an XRechnung structure preview</h3><p class="uk-text-muted uk-margin-small-top">Enter representative invoice data to inspect the generated XML structure and calculated totals. This does not create or issue a customer invoice.</p></div>
      <div><span class="uk-label">Preview only</span></div>
    </div>

    <details<?= $xmlResult !== null ? ' open' : '' ?>>
      <summary class="uk-button uk-button-default"><i class="fa fa-file-code-o"></i> <?= $xmlResult !== null ? 'Edit preview data' : 'Open preview builder' ?></summary>
      <form class="uk-form-stacked uk-margin-medium-top" method="post" action="<?= $e($adminUrl) ?>germany-format/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">

        <fieldset class="uk-fieldset">
          <legend class="uk-legend">Invoice details</legend>
          <p class="uk-text-muted">Identify the document and set the payment timeline.</p>
          <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@m" uk-grid>
            <div><label class="uk-form-label" for="germany-invoice-number">Invoice number</label><input class="uk-input uk-margin-small-top" id="germany-invoice-number" name="invoice_number" placeholder="RE-2026-001" maxlength="100" required><div class="uk-text-meta uk-margin-small-top">Use the number your accounting process assigns to this invoice.</div></div>
            <div><label class="uk-form-label" for="germany-issue-date">Issue date</label><input class="uk-input uk-margin-small-top" id="germany-issue-date" type="date" name="issue_date" value="<?= $e(date('Y-m-d')) ?>" required><div class="uk-text-meta uk-margin-small-top">The date the invoice is issued.</div></div>
            <div><label class="uk-form-label" for="germany-due-date">Due date</label><input class="uk-input uk-margin-small-top" id="germany-due-date" type="date" name="due_date" value="<?= $e(date('Y-m-d', strtotime('+14 days'))) ?>"><div class="uk-text-meta uk-margin-small-top">Optional. It cannot be earlier than the issue date.</div></div>
          </div>
        </fieldset>

        <hr>
        <div class="uk-grid-medium" uk-grid>
          <fieldset class="uk-fieldset uk-width-1-1 uk-width-1-2@l">
            <legend class="uk-legend">Seller</legend>
            <p class="uk-text-muted">The German business issuing the invoice.</p>
            <div class="uk-grid-small" uk-grid>
              <div class="uk-width-1-1"><label class="uk-form-label" for="germany-seller-name">Legal name</label><input class="uk-input uk-margin-small-top" id="germany-seller-name" name="seller_name" placeholder="Example GmbH" maxlength="255" required></div>
              <div class="uk-width-1-1"><label class="uk-form-label" for="germany-seller-street">Street and number</label><input class="uk-input uk-margin-small-top" id="germany-seller-street" name="seller_street" placeholder="Hauptstraße 1" maxlength="255" required></div>
              <div class="uk-width-1-2@s"><label class="uk-form-label" for="germany-seller-postal">Postal code</label><input class="uk-input uk-margin-small-top" id="germany-seller-postal" name="seller_postal_code" placeholder="10115" maxlength="20" required></div>
              <div class="uk-width-1-2@s"><label class="uk-form-label" for="germany-seller-city">City</label><input class="uk-input uk-margin-small-top" id="germany-seller-city" name="seller_city" placeholder="Berlin" maxlength="100" required></div>
              <div class="uk-width-1-1"><label class="uk-form-label" for="germany-seller-tax">VAT ID</label><input class="uk-input uk-margin-small-top" id="germany-seller-tax" name="seller_tax_id" placeholder="DE123456789" maxlength="32" autocomplete="off"><div class="uk-text-meta uk-margin-small-top">Optional. When provided, its checksum must be valid.</div></div>
            </div>
          </fieldset>

          <fieldset class="uk-fieldset uk-width-1-1 uk-width-1-2@l">
            <legend class="uk-legend">Buyer</legend>
            <p class="uk-text-muted">The customer receiving the invoice.</p>
            <div class="uk-grid-small" uk-grid>
              <div class="uk-width-1-1"><label class="uk-form-label" for="germany-buyer-name">Legal name</label><input class="uk-input uk-margin-small-top" id="germany-buyer-name" name="buyer_name" placeholder="Customer AG" maxlength="255" required></div>
              <div class="uk-width-1-1"><label class="uk-form-label" for="germany-buyer-street">Street and number</label><input class="uk-input uk-margin-small-top" id="germany-buyer-street" name="buyer_street" placeholder="Nebenstraße 2" maxlength="255" required></div>
              <div class="uk-width-1-2@s"><label class="uk-form-label" for="germany-buyer-postal">Postal code</label><input class="uk-input uk-margin-small-top" id="germany-buyer-postal" name="buyer_postal_code" placeholder="80331" maxlength="20" required></div>
              <div class="uk-width-1-2@s"><label class="uk-form-label" for="germany-buyer-city">City</label><input class="uk-input uk-margin-small-top" id="germany-buyer-city" name="buyer_city" placeholder="Munich" maxlength="100" required></div>
              <div class="uk-width-1-2@s"><label class="uk-form-label" for="germany-buyer-country">Country code</label><input class="uk-input uk-margin-small-top" id="germany-buyer-country" name="buyer_country_code" value="DE" minlength="2" maxlength="2" pattern="[A-Za-z]{2}" required><div class="uk-text-meta uk-margin-small-top">Use a two-letter country code.</div></div>
            </div>
          </fieldset>
        </div>

        <hr>
        <fieldset class="uk-fieldset">
          <legend class="uk-legend">Invoice line</legend>
          <p class="uk-text-muted">This preview supports one representative line item in EUR.</p>
          <div class="uk-grid-small" uk-grid>
            <div class="uk-width-1-1 uk-width-2-5@m"><label class="uk-form-label" for="germany-line-description">Description</label><input class="uk-input uk-margin-small-top" id="germany-line-description" name="line_description" placeholder="Consulting services" maxlength="255" required></div>
            <div class="uk-width-1-1 uk-width-1-5@m"><label class="uk-form-label" for="germany-quantity">Quantity</label><input class="uk-input uk-margin-small-top" id="germany-quantity" name="quantity" type="number" min="0.001" step="0.001" value="1" required></div>
            <div class="uk-width-1-1 uk-width-1-5@m"><label class="uk-form-label" for="germany-unit-price">Unit price · EUR</label><input class="uk-input uk-margin-small-top" id="germany-unit-price" name="unit_price" type="number" min="0.01" step="0.01" placeholder="100.00" required></div>
            <div class="uk-width-1-1 uk-width-1-5@m"><label class="uk-form-label" for="germany-tax-rate">VAT rate · %</label><input class="uk-input uk-margin-small-top" id="germany-tax-rate" name="tax_rate" type="number" min="0" max="100" step="0.01" value="19" required></div>
          </div>
        </fieldset>

        <div class="uk-flex uk-flex-right uk-margin-medium-top"><button class="uk-button uk-button-primary" type="submit"><i class="fa fa-cogs"></i> Generate structure preview</button></div>
      </form>
    </details>
  </section>

  <?php if ($xmlResult !== null): ?>
    <section class="uk-card uk-card-default uk-card-small uk-card-body uk-margin-medium-top">
      <div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Generated preview</p><h3 class="uk-card-title uk-margin-small-top"><?= $e($xmlResult['invoiceNumber']) ?></h3></div><div><span class="uk-label">Not certified</span></div></div>
      <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-3@s" uk-grid><div><div class="uk-text-meta">Net</div><strong><?= $e($money($xmlResult['netMinor'])) ?></strong></div><div><div class="uk-text-meta">VAT</div><strong><?= $e($money($xmlResult['taxMinor'])) ?></strong></div><div><div class="uk-text-meta">Gross</div><strong><?= $e($money($xmlResult['grossMinor'])) ?></strong></div></div>
      <details class="uk-margin-medium-top"><summary class="uk-button uk-button-default uk-button-small">View XML source</summary><pre class="uk-margin-small-top"><code><?= $e($xmlResult['xml']) ?></code></pre></details>
    </section>
  <?php endif; ?>
</div>
