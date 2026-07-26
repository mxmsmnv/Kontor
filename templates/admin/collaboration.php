<?php

/** @var \Kontor\Collaboration\Domain\Note[] $notes */
/** @var \Kontor\Collaboration\Domain\Comment[] $comments */
/** @var array<string, string> $taskLabels */
/** @var string $adminUrl */
/** @var callable $e */

$entityLink = static function (string $type, string $uid) use ($adminUrl, $e, $taskLabels): string {
    if ($type === 'task') {
        $label = $taskLabels[$uid] ?? $uid;
        return '<a href="' . $e($adminUrl) . 'task/?id=' . $e(rawurlencode($uid)) . '">' . $e($label) . '</a>';
    }

    return $e($type . ' · ' . $uid);
};
?>
<div class="kontor-shell">
  <header class="kontor-pagehead">
    <div>
      <p class="kontor-eyebrow">Team context</p>
      <h2>Collaboration</h2>
      <p>Recent notes and discussion attached to business records.</p>
    </div>
    <a class="kontor-button kontor-button--ghost" href="<?= $e($adminUrl) ?>tasks/"><i class="fa fa-check-square-o"></i> Tasks</a>
  </header>

  <section class="kontor-card kontor-tablewrap">
    <h3>Recent comments</h3>
    <?php if ($comments !== []): ?>
      <table class="kontor-table"><thead><tr><th>Record</th><th>Comment</th><th>Author</th><th>Created</th></tr></thead><tbody>
      <?php foreach ($comments as $comment): ?><tr>
        <td><?= $entityLink($comment->entityType, $comment->entityUid) ?></td>
        <td><?= $e($comment->body) ?></td>
        <td>User #<?= $e($comment->createdBy ?? 'system') ?></td>
        <td><?= $e($comment->createdAt->format('Y-m-d H:i')) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?><p>No comments yet. Open a task to start a discussion.</p><?php endif; ?>
  </section>

  <section class="kontor-card kontor-tablewrap">
    <h3>Recent notes</h3>
    <?php if ($notes !== []): ?>
      <table class="kontor-table"><thead><tr><th>Record</th><th>Note</th><th>Author</th><th>Created</th></tr></thead><tbody>
      <?php foreach ($notes as $note): ?><tr>
        <td><?= $entityLink($note->entityType, $note->entityUid) ?></td>
        <td><?= $e($note->body) ?></td>
        <td>User #<?= $e($note->createdBy ?? 'system') ?></td>
        <td><?= $e($note->createdAt->format('Y-m-d H:i')) ?></td>
      </tr><?php endforeach; ?>
      </tbody></table>
    <?php else: ?><p>No notes yet. Open a task to add one.</p><?php endif; ?>
  </section>
</div>
