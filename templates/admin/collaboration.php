<?php

/** @var \Kontor\Collaboration\Domain\Note[] $notes */
/** @var \Kontor\Collaboration\Domain\Comment[] $comments */
/** @var array<string, string> $taskLabels */
/** @var array<int, string> $authorLabels */
/** @var string $adminUrl */
/** @var callable $e */

$entityLink = static function (string $type, string $uid) use ($adminUrl, $e, $taskLabels): string {
    if ($type === 'task') {
        if (isset($taskLabels[$uid])) {
            return '<a href="' . $e($adminUrl) . 'task/?id=' . $e(rawurlencode($uid)) . '">' . $e($taskLabels[$uid]) . '</a>';
        }

        return $e('Unavailable task');
    }

    return $e(ucfirst(str_replace('_', ' ', $type)));
};
?>
<div class="ProcessKontor pw-module-workspace kontor-shell">
  <header class="pw-module-head kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Team context</p>
      <h2>Collaboration</h2>
      <p>Recent notes and discussion attached to business records.</p>
    </div>
    <a class="uk-button uk-button-secondary kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>tasks/"><i class="fa fa-check-square-o"></i> Tasks</a>
  </header>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <h3>Recent comments</h3>
    <?php if ($comments !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Record</th><th>Comment</th><th>Author</th><th>Created</th></tr></thead><tbody>
      <?php foreach ($comments as $comment): ?><tr>
        <td><?= $entityLink($comment->entityType, $comment->entityUid) ?></td>
        <td><?= $e($comment->body) ?></td>
        <td><?= $e($comment->createdBy !== null ? ($authorLabels[$comment->createdBy] ?? 'Former user') : 'System') ?></td>
        <td><?= $e($comment->createdAt->format('Y-m-d H:i')) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?><p>No comments yet. Open a task to start a discussion.</p><?php endif; ?>
  </section>

  <section class="uk-card uk-card-default uk-card-small uk-card-body kontor-card pw-table-panel uk-overflow-auto kontor-tablewrap">
    <h3>Recent notes</h3>
    <?php if ($notes !== []): ?>
      <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small kontor-table"><thead><tr><th>Record</th><th>Note</th><th>Author</th><th>Created</th></tr></thead><tbody>
      <?php foreach ($notes as $note): ?><tr>
        <td><?= $entityLink($note->entityType, $note->entityUid) ?></td>
        <td><?= $e($note->body) ?></td>
        <td><?= $e($note->createdBy !== null ? ($authorLabels[$note->createdBy] ?? 'Former user') : 'System') ?></td>
        <td><?= $e($note->createdAt->format('Y-m-d H:i')) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?><p>No notes yet. Open a task to add one.</p><?php endif; ?>
  </section>
</div>
