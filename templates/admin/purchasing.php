<?php

/** @var \Kontor\Purchasing\Domain\Supplier[] $suppliers */
/** @var array<string, string> $supplierLabels */
/** @var \Kontor\Purchasing\Domain\PurchaseOrder[] $orders */
/** @var \Kontor\Purchasing\Domain\PurchaseOrder[] $allOrders */
/** @var string $query */
/** @var string $selectedStatus */
/** @var string[] $statuses */
/** @var bool $canCreateSupplier */
/** @var bool $canCreateOrder */
/** @var bool $canReceive */
/** @var bool $canViewSuppliers */
/** @var bool $canViewInventory */
/** @var bool $canViewExpenses */
/** @var string $adminUrl */
/** @var callable $e */

$today = new DateTimeImmutable('today');
$activeSuppliers = array_values(array_filter($suppliers, static fn ($supplier): bool => $supplier->isActive()));
$statusCounts = [];
$awaitingReceipt = 0;
$overdueCount = 0;
$receivedCount = 0;
$committedByCurrency = [];
foreach ($allOrders as $order) {
    $statusCounts[$order->status] = ($statusCounts[$order->status] ?? 0) + 1;
    if ($order->isReceivable()) {
        ++$awaitingReceipt;
        $currency = $order->total->currencyCode();
        $committedByCurrency[$currency] = ($committedByCurrency[$currency] ?? 0) + $order->total->amountMinor();
        if ($order->expectedDate !== null && $order->expectedDate < $today) {
            ++$overdueCount;
        }
    }
    if ($order->status === 'received') {
        ++$receivedCount;
    }
}
ksort($committedByCurrency);
$money = static fn (\Kontor\SDK\ValueObjects\Money $value): string =>
    number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$moneySummary = static function (array $amounts): string {
    if ($amounts === []) {
        return '—';
    }
    $currency = array_key_first($amounts);
    $summary = number_format($amounts[$currency] / 100, 2, '.', ',') . ' ' . $currency;

    return count($amounts) > 1 ? $summary . ' +' . (count($amounts) - 1) : $summary;
};
$statusLabel = static fn (string $status): string => ucwords(str_replace('_', ' ', $status));
$statusClass = static fn (string $status): string => match ($status) {
    'issued', 'partially_received' => ' uk-label-warning',
    'received' => ' uk-label-success',
    'cancelled' => ' uk-label-danger',
    default => '',
};
$filtersActive = $query !== '' || $selectedStatus !== '';
$canStartOrder = $canCreateOrder && $activeSuppliers !== [];
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div><p class="kontor-eyebrow">Operations · Buy side</p><h2>Purchasing</h2><p>Control supplier commitments from request and order through receipt and downstream cost review.</p></div>
    <div class="pw-module-actions kontor-pagehead__actions"><?php if ($canCreateSupplier): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>purchasing-supplier/"><i class="fa fa-address-card-o"></i> New supplier</a><?php endif; ?><?php if ($canStartOrder): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>purchase-order/"><i class="fa fa-plus"></i> New purchase order</a><?php endif; ?></div>
  </header>

  <div class="uk-grid-small uk-child-width-1-1 uk-child-width-1-2@s uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <?php if ($canViewSuppliers): ?><div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-address-card-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) count($activeSuppliers)) ?></strong><span class="kontor-stat__label">Active suppliers</span></span></div></div><?php endif; ?>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon"><i class="fa fa-file-text-o"></i></span><span><strong class="kontor-stat__value"><?= $e((string) ($statusCounts['draft'] ?? 0)) ?></strong><span class="kontor-stat__label">Draft orders to review</span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $overdueCount > 0 ? ' kontor-stat__icon--danger' : ($awaitingReceipt > 0 ? ' kontor-stat__icon--warning' : '') ?>"><i class="fa fa-truck"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $awaitingReceipt) ?></strong><span class="kontor-stat__label"><?= $overdueCount > 0 ? $e((string) $overdueCount) . ' overdue receipt' . ($overdueCount === 1 ? '' : 's') : 'Awaiting receipt' ?></span></span></div></div>
    <div><div class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat"><span class="kontor-stat__icon<?= $committedByCurrency !== [] ? ' kontor-stat__icon--warning' : '' ?>"><i class="fa fa-money"></i></span><span><strong class="kontor-stat__value"><?= $e($moneySummary($committedByCurrency)) ?></strong><span class="kontor-stat__label"><?= $committedByCurrency !== [] ? 'Open supplier commitment' : 'No open commitments' ?></span></span></div></div>
  </div>

  <div class="uk-alert-primary uk-margin-medium-bottom" uk-alert><h3 class="uk-h4"><i class="fa fa-info-circle"></i> About this workspace</h3><p>Purchasing connects approved suppliers, purchase orders and warehouse receipts. Issue an order only after checking its lines and dates; record receipt when goods arrive so inventory reflects what was actually delivered.</p></div>

  <div class="uk-grid-medium" uk-grid>
    <div class="uk-width-1-1 uk-width-2-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body">
        <div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Order control</p><h3 class="uk-card-title uk-margin-small-top uk-margin-remove-bottom">Purchase orders</h3><p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Review commitments, expected dates and receiving status from one queue.</p></div><div><span class="uk-label"><?= $e((string) count($allOrders)) ?> total</span></div></div>

        <?php if ($allOrders !== []): ?><form class="uk-form-stacked uk-margin-medium-top" method="get" action="<?= $e($adminUrl) ?>purchasing/"><div class="uk-grid-small uk-flex-bottom" uk-grid><div class="uk-width-1-1 uk-width-expand@s"><label class="uk-form-label" for="purchasing-search">Find an order</label><div class="uk-inline uk-width-1-1 uk-margin-small-top"><span class="uk-form-icon"><i class="fa fa-search"></i></span><input class="uk-input" id="purchasing-search" name="q" type="search" value="<?= $e($query) ?>" placeholder="<?= $canViewSuppliers ? 'Order number or supplier' : 'Order number' ?>" aria-describedby="purchasing-search-help"></div><div class="uk-text-meta uk-margin-small-top" id="purchasing-search-help"><?= $canViewSuppliers ? 'Searches purchase-order numbers and supplier names.' : 'Searches purchase-order numbers.' ?></div></div><div class="uk-width-1-1 uk-width-1-3@s"><label class="uk-form-label" for="purchasing-status">Status</label><select class="uk-select uk-margin-small-top" id="purchasing-status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= $e($status) ?>"<?= $selectedStatus === $status ? ' selected' : '' ?>><?= $e($statusLabel($status)) ?></option><?php endforeach; ?></select><div class="uk-text-meta uk-margin-small-top">Use receipt statuses to focus warehouse work.</div></div><div class="uk-width-1-1 uk-width-auto@s"><button class="uk-button uk-button-primary uk-width-1-1" type="submit">Apply</button></div><?php if ($filtersActive): ?><div class="uk-width-1-1 uk-width-auto@s"><a class="uk-button uk-button-default uk-link-reset uk-width-1-1" href="<?= $e($adminUrl) ?>purchasing/">Clear</a></div><?php endif; ?></div></form><?php endif; ?>

        <?php if ($orders !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($orders as $order): ?><?php $orderUid = $order->uid->toString(); $orderUrl = $adminUrl . 'purchase-order/?id=' . rawurlencode($orderUid); $isOverdue = $order->isReceivable() && $order->expectedDate !== null && $order->expectedDate < $today; ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-flex-wrap uk-grid-small" uk-grid><div class="uk-width-expand@m"><a class="uk-link-reset" href="<?= $e($orderUrl) ?>"><strong><i class="fa fa-file-text-o uk-margin-small-right"></i><?= $e($order->number ?? 'Draft purchase order') ?></strong></a><div class="uk-text-meta uk-margin-small-top"><?= $e($supplierLabels[$order->supplierUid] ?? ($canViewSuppliers ? 'Supplier record unavailable' : 'Restricted supplier')) ?></div></div><div class="uk-text-right@m"><strong><?= $e($money($order->total)) ?></strong><div class="uk-margin-small-top"><span class="uk-label<?= $statusClass($order->status) ?>"><?= $e($statusLabel($order->status)) ?></span></div></div></div><div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-small-top" uk-grid><div><span class="uk-text-meta">Expected</span><div class="uk-margin-small-top <?= $isOverdue ? 'uk-text-danger' : '' ?>"><strong><?= $order->expectedDate !== null ? $e($order->expectedDate->format('M j, Y')) : 'Not scheduled' ?></strong><?= $isOverdue ? ' · Overdue' : '' ?></div></div><div class="uk-flex uk-flex-middle uk-grid-small" uk-grid><?php if ($canReceive && $order->isReceivable()): ?><div><a class="uk-button uk-button-primary uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>purchasing-receipt/?order=<?= $e(rawurlencode($orderUid)) ?>"><i class="fa fa-cubes"></i> Record receipt</a></div><?php endif; ?><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($orderUrl) ?>">Open order <i class="fa fa-angle-right"></i></a></div></div></div></li><?php endforeach; ?></ul>
        <?php elseif ($filtersActive): ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-search fa-2x uk-text-muted"></i><h4>No matching purchase orders</h4><p class="uk-text-muted">Try another order number, supplier or status.</p><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>purchasing/">Clear filters</a></div>
        <?php elseif ($canViewSuppliers && $activeSuppliers === []): ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-address-card-o fa-2x uk-text-muted"></i><h4>Add a supplier first</h4><p class="uk-text-muted">A purchase order needs an active supplier with currency and payment terms.</p><?php if ($canCreateSupplier): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>purchasing-supplier/"><i class="fa fa-plus"></i> Add supplier</a><?php endif; ?></div>
        <?php else: ?><div class="uk-placeholder uk-text-center uk-margin-medium-top"><i class="fa fa-file-text-o fa-2x uk-text-muted"></i><h4>No purchase orders yet</h4><p class="uk-text-muted">Create a draft, add the required item and confirm its destination before issue.</p><?php if ($canStartOrder): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>purchase-order/"><i class="fa fa-plus"></i> New purchase order</a><?php endif; ?></div><?php endif; ?>
      </section>
    </div>

    <div class="uk-width-1-1 uk-width-1-3@l">
      <section class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Guided workflow</p><h3 class="uk-card-title uk-margin-small-top">From supplier to stock</h3><p class="uk-text-muted">Each stage leaves the previous decision visible and auditable.</p><ol class="uk-list uk-list-divider uk-margin-medium-top"><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">1</span><span><strong>Approve the supplier</strong><br><span class="uk-text-meta">Confirm contact details, currency and payment terms.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">2</span><span><strong>Review and issue</strong><br><span class="uk-text-meta">Check items, quantities, prices, warehouse and expected date.</span></span></div></li><li><div class="uk-flex uk-flex-top"><span class="uk-label uk-margin-small-right">3</span><span><strong>Receive what arrived</strong><br><span class="uk-text-meta">Record actual quantities so stock and remaining commitments stay accurate.</span></span></div></li></ol><?php if ($canStartOrder): ?><a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset uk-margin-top" href="<?= $e($adminUrl) ?>purchase-order/"><i class="fa fa-plus"></i> Start a draft order</a><?php elseif ($canCreateSupplier): ?><a class="uk-button uk-button-primary uk-width-1-1 uk-link-reset uk-margin-top" href="<?= $e($adminUrl) ?>purchasing-supplier/"><i class="fa fa-plus"></i> Add the first supplier</a><?php endif; ?></section>

    </div>
  </div>

  <div class="uk-grid-medium uk-margin-medium-top" uk-grid>
      <?php if ($canViewSuppliers): ?><div class="uk-width-1-1<?= ($canViewInventory || $canViewExpenses) ? ' uk-width-2-3@l' : '' ?>"><section class="uk-card uk-card-default uk-card-small uk-card-body"><div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Vendor directory</p><h3 class="uk-card-title uk-margin-small-top">Suppliers</h3></div><div><span class="uk-label"><?= $e((string) count($suppliers)) ?></span></div></div><?php if ($suppliers !== []): ?><ul class="uk-list uk-list-divider uk-margin-medium-top"><?php foreach ($suppliers as $supplier): ?><li><div class="uk-flex uk-flex-between uk-flex-top uk-grid-small" uk-grid><div class="uk-width-expand"><strong><?= $e($supplier->legalName) ?></strong><div class="uk-text-meta uk-margin-small-top"><?= $e($supplier->code) ?> · <?= $e((string) $supplier->paymentTermsDays) ?> day terms · <?= $e($supplier->currencyCode) ?></div><?php if ($supplier->email !== null || $supplier->phone !== null): ?><div class="uk-text-meta uk-margin-small-top"><?= $e($supplier->email ?? $supplier->phone ?? '') ?></div><?php endif; ?></div><div><span class="uk-label<?= $supplier->isActive() ? ' uk-label-success' : '' ?>"><?= $e($statusLabel($supplier->status)) ?></span></div></div></li><?php endforeach; ?></ul><?php else: ?><p class="uk-text-muted">No suppliers are available yet.</p><?php endif; ?><?php if ($canCreateSupplier): ?><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset uk-margin-top" href="<?= $e($adminUrl) ?>purchasing-supplier/"><i class="fa fa-address-card-o"></i> New supplier</a><?php endif; ?></section></div><?php endif; ?>
      <?php if ($canViewInventory || $canViewExpenses): ?><div class="uk-width-1-1 uk-width-1-3@l"><section class="uk-card uk-card-default uk-card-small uk-card-body"><p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Connected operations</p><h3 class="uk-card-title uk-margin-small-top">Continue the workflow</h3><p class="uk-text-muted">Open only the components available to your role and installation.</p><div class="uk-grid-small uk-child-width-1-1" uk-grid><?php if ($canViewInventory): ?><div><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>inventory/"><i class="fa fa-cubes"></i> Inventory</a></div><?php endif; ?><?php if ($canViewExpenses): ?><div><a class="uk-button uk-button-default uk-width-1-1 uk-link-reset" href="<?= $e($adminUrl) ?>expenses/"><i class="fa fa-credit-card"></i> Expenses</a></div><?php endif; ?></div></section></div><?php endif; ?>
  </div>
</div>
