<?php

/** @var \Kontor\CRM\Domain\Lead[] $leads */
/** @var string $query */
/** @var string|null $selectedStatus */
/** @var bool $showArchived */
/** @var int $page */
/** @var int $totalPages */
/** @var int $totalRecords */
/** @var array{all: int, new: int, contacted: int, qualified: int, converted: int, lost: int, archived: int} $leadCounts */
/** @var array<string, string> $customerLabels */
/** @var bool $canViewContact */
/** @var bool $canViewCompany */
/** @var bool $canViewDeals */
/** @var bool $canCreateLead */
/** @var bool $canArchiveLead */
/** @var bool $crmIntakeReady */
/** @var string $adminUrl */
/** @var string $csrfName */
/** @var string $csrfValue */
/** @var callable $e */

$pageUrl = static function (int $targetPage) use ($query, $selectedStatus, $showArchived): string {
    $parameters = http_build_query(array_filter([
        'q' => $query,
        'status' => $selectedStatus,
        'archived' => $showArchived ? 1 : '',
        'page' => $targetPage > 1 ? $targetPage : '',
    ], static fn (string|int|null $value): bool => $value !== null && $value !== ''));
    return $parameters === '' ? './' : './?' . $parameters;
};
$viewUrl = static function (?string $status = null, bool $archived = false) use ($query): string {
    $parameters = http_build_query(array_filter([
        'q' => $query,
        'status' => $status,
        'archived' => $archived ? 1 : '',
    ], static fn (string|int|null $value): bool => $value !== null && $value !== ''));
    return $parameters === '' ? './' : './?' . $parameters;
};
$clearSearchParameters = http_build_query(array_filter([
    'status' => $selectedStatus,
    'archived' => $showArchived ? 1 : '',
], static fn (string|int|null $value): bool => $value !== null && $value !== ''));
$clearSearchUrl = $clearSearchParameters === '' ? './' : './?' . $clearSearchParameters;
$money = static fn (?\Kontor\SDK\ValueObjects\Money $value): string => $value === null
    ? 'Not estimated'
    : number_format($value->amountMinor() / 100, 2, '.', ',') . ' ' . $value->currencyCode();
$humanize = static fn (?string $value): string => $value === null || $value === ''
    ? 'Not set'
    : ucwords(str_replace('_', ' ', $value));
$customer = static function ($lead) use ($customerLabels, $canViewContact, $canViewCompany, $adminUrl): array {
    $type = $lead->companyUid !== null ? 'company' : ($lead->contactUid !== null ? 'contact' : '');
    $uid = $type === 'company' ? $lead->companyUid : ($type === 'contact' ? $lead->contactUid : null);
    $label = $type !== '' && $uid !== null ? ($customerLabels[$type . ':' . $uid] ?? '') : '';
    if (str_contains($label, ' · ')) {
        $label = explode(' · ', $label, 2)[1];
    }
    return [
        'label' => $label !== '' ? $label : ($type !== '' ? ucfirst($type) . ' connected' : 'No customer connected'),
        'url' => $uid !== null && (($type === 'contact' && $canViewContact) || ($type === 'company' && $canViewCompany))
            ? $adminUrl . $type . '/?id=' . rawurlencode($uid)
            : null,
    ];
};
$statusClass = static fn (string $status): string => match ($status) {
    'qualified' => ' uk-label-success',
    'lost' => ' uk-label-warning',
    default => '',
};
$directoryTitle = $showArchived
    ? 'Archived leads'
    : ($selectedStatus !== null ? $humanize($selectedStatus) . ' leads' : 'Lead pipeline');
$now = new \DateTimeImmutable();
?>
<div class="ProcessKontor pw-module-workspace kontor-shell kontor-crm-workspace">
  <header class="uk-grid-small uk-flex-middle uk-margin-medium-bottom" uk-grid>
    <div class="uk-width-1-1 uk-width-expand@m">
      <p class="uk-text-meta uk-text-uppercase uk-margin-remove-bottom">Revenue · Lead qualification</p>
      <p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Capture demand, connect the right customer and keep every opportunity moving toward qualification or a clear outcome.</p>
    </div>
    <div class="uk-width-1-1 uk-width-auto@m">
      <div class="uk-flex uk-flex-wrap uk-grid-small" uk-grid>
        <?php if ($canViewDeals): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>crm-deals/"><i class="fa fa-columns"></i> Deal pipeline</a></div><?php endif; ?>
        <?php if ($crmIntakeReady): ?><div><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($adminUrl) ?>crm-intake/"><i class="fa fa-list-alt"></i> Intake profile</a></div><?php endif; ?>
        <?php if ($canCreateLead): ?><div><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>crm-lead/"><i class="fa fa-plus"></i> New lead</a></div><?php endif; ?>
      </div>
    </div>
  </header>

  <section class="kontor-section-intro uk-margin-bottom" aria-label="About this section">
    <i class="fa fa-info-circle" aria-hidden="true"></i>
    <div>
      <strong>From first signal to qualified opportunity</strong>
      <p>Start with a new lead, record the next customer action, qualify real demand and convert it into a deal. Lost and archived records remain available for history without cluttering active work.</p>
    </div>
  </section>

  <div class="uk-grid-small uk-child-width-1-2 uk-child-width-1-4@l uk-margin-medium-bottom" uk-grid>
    <div><a class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat uk-link-reset<?= !$showArchived && $selectedStatus === null ? ' kontor-card--selected' : '' ?>" href="<?= $e($viewUrl()) ?>"<?= !$showArchived && $selectedStatus === null ? ' aria-current="page"' : '' ?>><span class="kontor-stat__icon"><i class="fa fa-filter"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $leadCounts['all']) ?></strong><span class="kontor-stat__label">All current leads</span></span></a></div>
    <div><a class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat uk-link-reset<?= !$showArchived && $selectedStatus === 'qualified' ? ' kontor-card--selected' : '' ?>" href="<?= $e($viewUrl('qualified')) ?>"<?= !$showArchived && $selectedStatus === 'qualified' ? ' aria-current="page"' : '' ?>><span class="kontor-stat__icon kontor-stat__icon--success"><i class="fa fa-check-circle"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $leadCounts['qualified']) ?></strong><span class="kontor-stat__label">Qualified</span></span></a></div>
    <div><a class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat uk-link-reset<?= !$showArchived && $selectedStatus === 'converted' ? ' kontor-card--selected' : '' ?>" href="<?= $e($viewUrl('converted')) ?>"<?= !$showArchived && $selectedStatus === 'converted' ? ' aria-current="page"' : '' ?>><span class="kontor-stat__icon"><i class="fa fa-exchange"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $leadCounts['converted']) ?></strong><span class="kontor-stat__label">Converted to deals</span></span></a></div>
    <div><a class="uk-card uk-card-default uk-card-small uk-card-body kontor-stat uk-link-reset<?= $showArchived ? ' kontor-card--selected' : '' ?>" href="<?= $e($viewUrl(null, true)) ?>"<?= $showArchived ? ' aria-current="page"' : '' ?>><span class="kontor-stat__icon"><i class="fa fa-archive"></i></span><span><strong class="kontor-stat__value"><?= $e((string) $leadCounts['archived']) ?></strong><span class="kontor-stat__label">Archived history</span></span></a></div>
  </div>

  <nav aria-label="Lead views">
    <ul class="uk-tab uk-margin-remove-bottom">
      <li<?= !$showArchived && $selectedStatus === null ? ' class="uk-active"' : '' ?>><a href="<?= $e($viewUrl()) ?>">All <span class="uk-badge"><?= $e((string) $leadCounts['all']) ?></span></a></li>
      <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'converted' => 'Converted', 'lost' => 'Lost'] as $status => $label): ?>
        <li<?= !$showArchived && $selectedStatus === $status ? ' class="uk-active"' : '' ?>><a href="<?= $e($viewUrl($status)) ?>"><?= $e($label) ?> <span class="uk-badge"><?= $e((string) $leadCounts[$status]) ?></span></a></li>
      <?php endforeach; ?>
      <li<?= $showArchived ? ' class="uk-active"' : '' ?>><a href="<?= $e($viewUrl(null, true)) ?>">Archive <span class="uk-badge"><?= $e((string) $leadCounts['archived']) ?></span></a></li>
    </ul>
  </nav>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card">
    <form class="uk-form-stacked uk-margin-bottom" method="get" action="./" role="search">
      <?php if ($showArchived): ?><input type="hidden" name="archived" value="1"><?php endif; ?>
      <?php if (!$showArchived && $selectedStatus !== null): ?><input type="hidden" name="status" value="<?= $e($selectedStatus) ?>"><?php endif; ?>
      <div class="uk-grid-small uk-flex-middle" uk-grid>
        <div class="uk-width-1-1 uk-width-expand@s">
          <div class="uk-search uk-search-default uk-width-1-1"><span uk-search-icon></span><input class="uk-search-input" name="q" type="search" value="<?= $e($query) ?>" placeholder="Search opportunity, source or description…" aria-label="Search leads"></div>
          <div class="uk-text-meta uk-margin-small-top">Search narrows the current view without changing pipeline data.</div>
        </div>
        <div class="uk-width-auto@s"><button class="uk-button uk-button-primary" type="submit">Search</button></div>
        <?php if ($query !== ''): ?><div class="uk-width-auto@s"><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($clearSearchUrl) ?>">Clear</a></div><?php endif; ?>
      </div>
    </form>

    <header class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap uk-grid-small uk-margin-small-bottom" uk-grid>
      <div><h3 class="uk-h4 uk-margin-remove"><?= $e($directoryTitle) ?></h3><p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom"><?= $e((string) $totalRecords) ?> result<?= $totalRecords === 1 ? '' : 's' ?><?= $query !== '' ? ' for “' . $e($query) . '”' : '' ?></p></div>
      <?php if ($totalPages > 1): ?><div><span class="uk-text-meta">Page <?= $e((string) $page) ?> of <?= $e((string) $totalPages) ?></span></div><?php endif; ?>
    </header>

    <?php if ($leads): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small uk-table-responsive kontor-table kontor-directorytable">
        <thead><tr><th>Opportunity</th><th>Priority</th><th>Estimated value</th><th>Next customer action</th><th>Status</th><th><span class="kontor-visually-hidden">Actions</span></th></tr></thead>
        <tbody>
          <?php foreach ($leads as $lead): ?>
            <?php $leadCustomer = $customer($lead); $isOpen = in_array($lead->status, ['new', 'contacted', 'qualified'], true); $isOverdue = $isOpen && $lead->nextActionAt !== null && $lead->nextActionAt < $now; ?>
            <tr>
              <td><div class="kontor-identity"><span class="kontor-avatar"><i class="fa fa-bullseye"></i></span><span class="kontor-identity__body"><strong><?= $e($lead->title) ?></strong><span class="uk-text-meta"><?= $e($lead->source ?: 'Source not recorded') ?><?php if ($leadCustomer['url'] !== null): ?> · <a class="uk-link-reset" href="<?= $e($leadCustomer['url']) ?>"><?= $e($leadCustomer['label']) ?></a><?php elseif ($leadCustomer['label'] !== 'No customer connected'): ?> · <?= $e($leadCustomer['label']) ?><?php endif; ?></span></span></div></td>
              <td data-label="Priority"><span class="kontor-priority kontor-priority--<?= $e($lead->priority) ?>"><?= $e($humanize($lead->priority)) ?></span></td>
              <td data-label="Estimated value"><strong><?= $e($money($lead->estimatedValue)) ?></strong></td>
              <td data-label="Next customer action"><?php if (!$isOpen): ?><span class="uk-text-muted"><?= $lead->status === 'converted' ? 'Converted to a deal' : 'Lead closed' ?></span><?php elseif ($lead->nextActionAt !== null): ?><time datetime="<?= $e($lead->nextActionAt->format(DATE_ATOM)) ?>" class="<?= $isOverdue ? 'uk-text-danger' : '' ?>"><?= $e($lead->nextActionAt->format('M j, Y · H:i')) ?></time><?php if ($isOverdue): ?><span class="uk-text-meta uk-display-block"><i class="fa fa-exclamation-circle"></i> Follow-up overdue</span><?php endif; ?><?php else: ?><span class="uk-text-muted">Not scheduled</span><?php endif; ?></td>
              <td data-label="Status"><span class="uk-label<?= $statusClass($lead->status) ?>"><?= $e($humanize($lead->status)) ?></span></td>
              <td class="kontor-rowaction"><div class="uk-flex uk-flex-middle uk-flex-right uk-grid-small" uk-grid><div><a class="uk-button uk-button-default uk-button-small uk-link-reset" href="<?= $e($adminUrl) ?>crm-lead/?id=<?= $e(rawurlencode($lead->uid->toString())) ?>">Open</a></div><?php if ($canArchiveLead): ?><div><form method="post" action="<?= $e($adminUrl) ?>crm-lead-action/" data-kontor-confirm="<?= $e(($showArchived ? 'Restore ' : 'Archive ') . $lead->title . '?') ?>"><input type="hidden" name="<?= $e($csrfName) ?>" value="<?= $e($csrfValue) ?>"><input type="hidden" name="id" value="<?= $e($lead->uid->toString()) ?>"><input type="hidden" name="action" value="<?= $showArchived ? 'restore' : 'archive' ?>"><input type="hidden" name="return_q" value="<?= $e($query) ?>"><input type="hidden" name="return_status" value="<?= $e($selectedStatus ?? '') ?>"><input type="hidden" name="return_archived" value="<?= $showArchived ? '1' : '0' ?>"><button class="uk-button uk-button-text" type="submit"><?= $showArchived ? 'Restore' : 'Archive' ?></button></form></div><?php endif; ?></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <div class="uk-placeholder uk-text-center"><i class="fa fa-bullseye fa-2x uk-text-muted"></i><h3 class="uk-margin-small-top uk-margin-small-bottom"><?= $query !== '' ? 'No matching leads' : ($showArchived ? 'The lead archive is empty' : 'No leads in this view') ?></h3><p class="uk-text-muted uk-margin-remove-top"><?= $query !== '' ? 'Try a broader search or clear the current query.' : ($showArchived ? 'Archived leads remain available here for reference.' : 'Capture a new opportunity or choose another pipeline stage.') ?></p><?php if ($query !== ''): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($clearSearchUrl) ?>">Clear search</a><?php elseif (!$showArchived && $canCreateLead): ?><a class="uk-button uk-button-primary uk-link-reset" href="<?= $e($adminUrl) ?>crm-lead/"><i class="fa fa-plus"></i> New lead</a><?php endif; ?></div>
    <?php endif; ?>
  </section>

  <?php if ($totalPages > 1): ?><nav class="kontor-pagination" aria-label="CRM lead pages"><span>Page <?= $e((string) $page) ?> of <?= $e((string) $totalPages) ?></span><div><?php if ($page > 1): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($pageUrl($page - 1)) ?>"><i class="fa fa-chevron-left"></i> Previous</a><?php endif; ?><?php if ($page < $totalPages): ?><a class="uk-button uk-button-default uk-link-reset" href="<?= $e($pageUrl($page + 1)) ?>">Next <i class="fa fa-chevron-right"></i></a><?php endif; ?></div></nav><?php endif; ?>
</div>
