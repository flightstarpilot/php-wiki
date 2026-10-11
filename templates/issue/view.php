<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = $issue['group_slug'] ?? '';

ob_start();
?>
<article class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b">
    <div class="flex items-start gap-3 flex-wrap">
      <span class="<?= $issue['status'] === 'open' ? 'text-green-600' : 'text-slate-400' ?>">
        <?= icon('alert', 'w-5 h-5') ?>
      </span>
      <h1 class="text-xl font-bold flex-1">
        <?= e($issue['title']) ?>
        <span class="text-slate-400 font-normal">#<?= (int)$issue['id'] ?></span>
      </h1>
      <span class="text-xs px-2 py-1 rounded <?= $issue['status'] === 'open' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>">
        <?= e($t('issue.status.' . $issue['status'])) ?>
      </span>
    </div>
    <div class="mt-2 text-xs text-slate-400 flex items-center gap-3 flex-wrap">
      <span><?= e($t('issue.by', ['user' => $issue['author'] ?? '-'])) ?></span>
      <span class="flex items-center gap-1"><?= icon('clock', 'w-3 h-3') ?><?= e($issue['created_at']) ?></span>
      <?php if (!empty($issue['group_slug'])): ?>
        <a href="<?= e(url('page/' . $issue['group_slug'])) ?>" class="text-blue-600 hover:underline flex items-center gap-1">
          <?= icon('file', 'w-3 h-3') ?><?= e($issue['group_slug']) ?>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <div class="px-6 py-4 whitespace-pre-wrap text-sm text-slate-700"><?= e($issue['content']) ?></div>

  <?php if ($canManage): ?>
    <div class="px-6 py-3 border-t flex items-center gap-2 flex-wrap">
      <?php if ($issue['status'] === 'open'): ?>
        <form method="post" action="<?= e(url('issue/' . (int)$issue['id'] . '/close')) ?>" class="inline">
          <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
          <button class="text-sm px-3 py-1.5 rounded border text-slate-600 hover:bg-slate-50">
            <?= e($t('issue.close')) ?>
          </button>
        </form>
      <?php else: ?>
        <form method="post" action="<?= e(url('issue/' . (int)$issue['id'] . '/reopen')) ?>" class="inline">
          <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
          <button class="text-sm px-3 py-1.5 rounded border text-slate-600 hover:bg-slate-50">
            <?= e($t('issue.reopen')) ?>
          </button>
        </form>
      <?php endif; ?>

      <?php if (\Wiki\Auth\Auth::isAdmin()): ?>
        <form method="post" action="<?= e(url('issue/' . (int)$issue['id'] . '/delete')) ?>" class="inline ml-auto"
              onsubmit="return confirm('<?= e($t('issue.confirm_delete')) ?>')">
          <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
          <button class="text-sm px-3 py-1.5 rounded text-red-600 hover:bg-red-50">
            <?= e($t('issue.delete')) ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</article>

<?php
$comments      = $comments;
$commentAction = url('issue/' . (int)$issue['id'] . '/comment');
$canComment    = \Wiki\Auth\Auth::check();
require WIKI_ROOT . '/templates/partials/comments.php';
?>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';