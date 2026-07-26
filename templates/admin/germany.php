<?php

/** @var string $countryCode */
/** @var string[] $documentFormats */
/** @var array<int, array{definition: array{code: string, name: string, type: string}, account: \Kontor\Ledger\Domain\Account|null, compatible: bool}> $chartStatus */
/** @var array{taxId: string, valid: bool}|null $taxResult */
/** @var array{invoiceNumber: string, xml: string, bytes: int, netMinor: int, taxMinor: int, grossMinor: int}|null $xmlResult */
/** @var bool $canConfigure */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */
$installed = count(array_filter($chartStatus, static fn (array $row): bool => $row['account'] !== null));
$conflicts = count(array_filter($chartStatus, static fn (array $row): bool => !$row['compatible']));
$money = static fn (int $minor): string => number_format($minor / 100, 2, '.', '') . ' EUR';
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Localization · <?= $e($countryCode) ?></p>
      <h2>Germany</h2>
      <p>German VAT checksum validation, an illustrative SKR03 account set, and country-specific document output.</p>
    </div>
    <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>ledger/"><i class="fa fa-balance-scale"></i> Open Ledger</a>
  </header>

  <section class="kontor-card">
    <header class="kontor-sectionhead">
      <div><p class="kontor-eyebrow">Localization capability</p><h3>Provider status</h3></div>
      <div><strong>localization.de</strong></div>
    </header>
    <div class="kontor-detailgrid">
      <div><span>Country</span><strong><?= $e($countryCode) ?></strong></div>
      <div><span>Document formats</span><strong><?= $e(implode(', ', $documentFormats)) ?></strong></div>
      <div><span>Tax validation</span><strong>Local checksum</strong></div>
      <div><span>External connections</span><strong>None</strong></div>
    </div>
    <p>XRechnung output here is an illustrative UBL-inspired subset, not certified EN16931/CIUS compliance. ZUGFeRD is advertised for future composition with the Documents PDF pipeline but is not generated yet.</p>
  </section>

  <section class="kontor-card">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">USt-IdNr.</p><h3>Validate VAT ID checksum</h3></div></header>
    <form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>germany-tax-id/">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield kontor-nativefield--wide"><span>German VAT ID *</span><input name="tax_id" value="DE811569869" maxlength="32" required></label>
      <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit">Validate VAT ID</button></div>
    </form>
    <?php if ($taxResult !== null): ?>
      <div class="kontor-detailgrid">
        <div><span>VAT ID</span><strong><?= $e($taxResult['taxId']) ?></strong></div>
        <div><span>Checksum result</span><strong><?= $e($taxResult['valid'] ? 'valid' : 'invalid') ?></strong></div>
      </div>
      <p>This verifies only the identifier’s internal checksum, not its registration with BZSt or VIES.</p>
    <?php endif; ?>
  </section>

  <section class="kontor-card kontor-tablewrap">
    <header class="kontor-sectionhead">
      <div><p class="kontor-eyebrow">Illustrative subset</p><h3>SKR03 account seed</h3></div>
      <div><strong><?= $e((string) $installed) ?>/<?= $e((string) count($chartStatus)) ?> present<?= $conflicts ? ' · ' . $e((string) $conflicts) . ' conflicts' : '' ?></strong></div>
    </header>
    <table class="kontor-table">
      <thead><tr><th>Code</th><th>German name</th><th>Type</th><th>Currency</th><th>Status</th></tr></thead>
      <tbody><?php foreach ($chartStatus as $row): ?><tr>
        <td><strong><?= $e($row['definition']['code']) ?></strong></td>
        <td><?= $e($row['definition']['name']) ?></td>
        <td><?= $e($row['definition']['type']) ?></td>
        <td>EUR</td>
        <td><span class="kontor-pill<?= (!$row['compatible'] || ($row['account'] && !$row['account']->isActive())) ? ' kontor-pill--inactive' : '' ?>"><?= $e(
            !$row['compatible']
                ? 'conflict'
                : ($row['account'] === null
                    ? 'missing'
                    : ($row['account']->isActive() ? 'ready' : 'archived'))
        ) ?></span></td>
      </tr><?php endforeach; ?></tbody>
    </table>
    <?php if ($canConfigure): ?>
      <form method="post" action="<?= $e($adminUrl) ?>germany-seed-chart/">
        <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
        <button class="kontor-button" type="submit"<?= $conflicts ? ' disabled' : '' ?>>Seed missing German accounts</button>
      </form>
    <?php endif; ?>
  </section>

  <section class="kontor-card">
    <header class="kontor-sectionhead"><div><p class="kontor-eyebrow">Country document bench</p><h3>Generate XRechnung preview</h3></div></header>
    <form class="kontor-nativeform" method="post" action="<?= $e($adminUrl) ?>germany-format/">
      <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
      <label class="kontor-nativefield"><span>Invoice number *</span><input name="invoice_number" value="RE-2026-001" required></label>
      <label class="kontor-nativefield"><span>Issue date *</span><input type="date" name="issue_date" value="<?= $e(date('Y-m-d')) ?>" required></label>
      <label class="kontor-nativefield"><span>Due date</span><input type="date" name="due_date" value="<?= $e(date('Y-m-d', strtotime('+14 days'))) ?>"></label>

      <label class="kontor-nativefield kontor-nativefield--wide"><span>Seller name *</span><input name="seller_name" value="Example GmbH" required></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Seller street *</span><input name="seller_street" value="Hauptstraße 1" required></label>
      <label class="kontor-nativefield"><span>Seller city *</span><input name="seller_city" value="Berlin" required></label>
      <label class="kontor-nativefield"><span>Seller postal code *</span><input name="seller_postal_code" value="10115" required></label>
      <label class="kontor-nativefield"><span>Seller VAT ID</span><input name="seller_tax_id" placeholder="DE123456789"></label>

      <label class="kontor-nativefield kontor-nativefield--wide"><span>Buyer name *</span><input name="buyer_name" value="Kunde AG" required></label>
      <label class="kontor-nativefield kontor-nativefield--wide"><span>Buyer street *</span><input name="buyer_street" value="Nebenstraße 2" required></label>
      <label class="kontor-nativefield"><span>Buyer city *</span><input name="buyer_city" value="München" required></label>
      <label class="kontor-nativefield"><span>Buyer postal code *</span><input name="buyer_postal_code" value="80331" required></label>
      <label class="kontor-nativefield"><span>Buyer country *</span><input name="buyer_country_code" value="DE" maxlength="2" required></label>

      <label class="kontor-nativefield kontor-nativefield--wide"><span>Line description *</span><input name="line_description" value="Consulting services" required></label>
      <label class="kontor-nativefield"><span>Quantity *</span><input name="quantity" value="10" inputmode="decimal" required></label>
      <label class="kontor-nativefield"><span>Unit price (EUR) *</span><input name="unit_price" value="100.00" inputmode="decimal" required></label>
      <label class="kontor-nativefield"><span>Tax rate (%) *</span><input name="tax_rate" value="19" inputmode="decimal" required></label>
      <div class="kontor-nativeform__actions"><button class="kontor-button" type="submit">Generate XRechnung preview</button></div>
    </form>
  </section>

  <?php if ($xmlResult !== null): ?>
    <section class="kontor-card">
      <header class="kontor-sectionhead">
        <div><p class="kontor-eyebrow"><?= $e($xmlResult['invoiceNumber']) ?></p><h3>XRechnung XML preview</h3></div>
        <div><strong><?= $e(number_format($xmlResult['bytes'])) ?> bytes</strong></div>
      </header>
      <div class="kontor-detailgrid">
        <div><span>Net</span><strong><?= $e($money($xmlResult['netMinor'])) ?></strong></div>
        <div><span>Tax</span><strong><?= $e($money($xmlResult['taxMinor'])) ?></strong></div>
        <div><span>Gross</span><strong><?= $e($money($xmlResult['grossMinor'])) ?></strong></div>
        <div><span>Encoding</span><strong>UTF-8 XML</strong></div>
      </div>
      <pre><code><?= $e($xmlResult['xml']) ?></code></pre>
    </section>
  <?php endif; ?>
</div>
