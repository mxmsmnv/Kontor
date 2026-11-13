<?php
$calendarUrl = $adminUrl . 'tasks/?view=calendar&month=';
$leading = (int) $start->format('N') - 1;
$dayCount = (int) $start->format('t');
$today = (new DateTimeImmutable())->format('Y-m-d');
?>
<nav class="uk-flex uk-flex-between uk-flex-wrap uk-margin" aria-label="Calendar navigation">
    <a class="uk-button uk-button-default" href="<?= $e($adminUrl . 'tasks/') ?>">Task list</a>
    <a class="uk-button uk-button-default" href="<?= $e($calendarUrl . $start->modify('-1 month')->format('Y-m')) ?>">Previous month</a>
    <a class="uk-button uk-button-default" href="<?= $e($calendarUrl . $start->modify('+1 month')->format('Y-m')) ?>">Next month</a>
</nav>
<h2><?= $e($start->format('F Y')) ?></h2>
<div class="uk-overflow-auto">
<table class="uk-table uk-table-divider uk-table-small" style="table-layout: fixed; min-width: 42rem">
    <thead><tr><?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday): ?>
        <th scope="col"><?= $weekday ?></th>
    <?php endforeach; ?></tr></thead>
    <tbody>
    <?php for ($row = 0; $row < (int) ceil(($leading + $dayCount) / 7); $row++): ?>
        <tr>
        <?php for ($col = 0; $col < 7; $col++): ?>
            <?php $day = 7 * $row + $col - $leading + 1; ?>
            <td>
            <?php if ($day >= 1 && $day <= $dayCount): ?>
                <time<?= $today === $start->format('Y-m-') . sprintf('%02d', $day) ? ' aria-current="date"' : '' ?>><?= $day ?></time>
                <ul class="uk-list uk-margin-small-top">
                    <?php foreach ($days[$day] ?? [] as $task): ?>
                        <li><a href="<?= $e($adminUrl . 'task/?id=' . $task->uid->toString()) ?>"><?= $e($task->title) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            </td>
        <?php endfor; ?>
        </tr>
    <?php endfor; ?>
    </tbody>
</table>
</div>
