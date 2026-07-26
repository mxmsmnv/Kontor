<?php

/** @var \Kontor\CRM\Domain\Lead[] $leads */
/** @var string $query */
/** @var string|null $selectedStatus */
/** @var bool $showArchived */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalRecords */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$url = static function (int $targetPage, bool $archived) use ($query, $selectedStatus): string {
    $parameters = array_filter([
        'q' => $query,
        'status' => $selectedStatus,
        'archived' => $archived ? 1 : null,
        'page' => $targetPage > 1 ? $targetPage : null,
    ], static fn (string|int|null $value): bool => $value !== null && $value !== '');

    return $parameters === [] ? './' : './?' . http_build_query($parameters);
};
$money = static function (?\Kontor\SDK\ValueObjects\Money $value): string {
    if ($value === null) {
        return '—';
    }

    return number_format($value->amountMinor() / 100, 2, '.', '') . ' ' . $value->currencyCode();
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Revenue pipeline</p>
      <h2>CRM · Leads</h2>
      <p>Capture opportunities, qualify them, and connect them to customers.</p>
    </div>
    <a class="kontor-button" href="<?= $e($adminUrl) ?>crm-lead/">
      <i class="fa fa-plus"></i> New lead
    </a>
  </header>

  <form class="kontor-toolbar" method="get" action="./">
    <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
    <label class="kontor-searchfield">
      <i class="fa fa-search"></i>
      <input name="q" type="search" value="<?= $e($query) ?>" placeholder="Lead title, source or description">
    </label>
    <select name="status" aria-label="Lead status">
      <option value="">All statuses</option>
      <?php foreach (['new', 'contacted', 'qualified', 'converted', 'lost'] as $status): ?>
        <option value="<?= $e($status) ?>"<?= $selectedStatus === $status ? ' selected' : '' ?>><?= $e(ucfirst($status)) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="kontor-button" type="submit">Filter</button>
    <?php if ($query !== '' || $selectedStatus !== null): ?>
      <a class="kontor-viewtoggle" href="<?= $showArchived ? './?archived=1' : './' ?>">
        <i class="fa fa-times"></i> Clear filters
      </a>
    <?php endif; ?>
    <a class="kontor-viewtoggle" href="<?= $e($url(1, !$showArchived)) ?>">
      <i class="fa fa-<?= $showArchived ? 'handshake-o' : 'archive' ?>"></i>
      <?= $showArchived ? 'Active leads' : 'Archive' ?>
    </a>
    <span class="kontor-secondary"><?= $e($totalRecords) ?> total · <?= $e(count($leads)) ?> shown</span>
  </form>

  <section class="kontor-card kontor-tablewrap kontor-directorytable">
    <?php if ($leads): ?>
      <table class="kontor-table">
        <thead>
          <tr><th>Lead</th><th>Priority</th><th>Value</th><th>Next action</th><th>Status</th><th><span class="kontor-visually-hidden">Actions</span></th></tr>
        </thead>
        <tbody>
          <?php foreach ($leads as $lead): ?>
            <tr>
              <td>
                <strong><a href="<?= $e($adminUrl) ?>crm-lead/?id=<?= $e(rawurlencode($lead->uid->toString())) ?>"><?= $e($lead->title) ?></a></strong>
                <span class="kontor-secondary"><?= $e($lead->source ?: 'No source') ?></span>
              </td>
              <td><?= $e(ucfirst($lead->priority)) ?></td>
              <td><?= $e($money($lead->estimatedValue)) ?></td>
              <td><?= $e($lead->nextActionAt?->format('Y-m-d H:i') ?? '—') ?></td>
              <td><span class="kontor-pill<?= $lead->status === 'new' || $lead->status === 'qualified' ? '' : ' kontor-pill--inactive' ?>"><?= $e($lead->status) ?></span></td>
              <td class="kontor-rowaction">
                <form method="post" action="<?= $e($adminUrl) ?>crm-lead-action/">
                  <input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>">
                  <input type="hidden" name="id" value="<?= $e($lead->uid->toString()) ?>">
                  <input type="hidden" name="action" value="<?= $showArchived ? 'restore' : 'archive' ?>">
                  <input type="hidden" name="return_q" value="<?= $e($query) ?>">
                  <input type="hidden" name="return_status" value="<?= $e($selectedStatus ?? '') ?>">
                  <input type="hidden" name="return_archived" value="<?= $showArchived ? '1' : '0' ?>">
                  <button type="submit" aria-label="<?= $showArchived ? 'Restore lead' : 'Archive lead' ?>" title="<?= $showArchived ? 'Restore lead' : 'Archive lead' ?>">
                    <i class="fa fa-<?= $showArchived ? 'undo' : 'archive' ?>"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="kontor-empty">
        <i class="fa fa-handshake-o"></i>
        <h3><?= $query !== '' || $selectedStatus !== null || $showArchived ? 'No matching leads' : 'No leads yet' ?></h3>
        <p><?= $showArchived ? 'Archived leads will appear here.' : 'Create the first opportunity for your sales pipeline.' ?></p>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($totalPages > 1): ?>
    <nav class="kontor-pagination" aria-label="CRM lead pages">
      <span>Page <?= $e($page) ?> of <?= $e($totalPages) ?></span>
      <div>
        <?php if ($page > 1): ?><a class="kontor-button kontor-button--ghost" href="<?= $e($url($page - 1, $showArchived)) ?>">Previous</a><?php endif; ?>
        <?php if ($page < $totalPages): ?><a class="kontor-button kontor-button--ghost" href="<?= $e($url($page + 1, $showArchived)) ?>">Next</a><?php endif; ?>
      </div>
    </nav>
  <?php endif; ?>
</div>
