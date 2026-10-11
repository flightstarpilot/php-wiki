<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
?>
<section class="bg-white rounded shadow mt-4">
  <header class="px-6 py-3 border-b flex items-center gap-2">
    <?= icon('file', 'w-4 h-4 text-slate-400') ?>
    <h2 class="font-bold"><?= e($t('comment.title')) ?></h2>
    <span class="text-sm text-slate-400">(<?= count($comments) ?>)</span>
  </header>

  <?php if (!$comments): ?>
    <div class="px-6 py-6 text-sm text-slate-500"><?= e($t('comment.empty')) ?></div>
  <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($comments as $c): ?>
        <li class="px-6 py-4 flex gap-3">
          <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-slate-500 shrink-0">
            <?= icon('user', 'w-4 h-4') ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 text-sm">
              <span class="font-medium"><?= e($c['author'] ?? '') ?></span>
              <span class="text-slate-400 flex items-center gap-1">
                <?= icon('clock', 'w-3 h-3') ?><span><?= e($c['created_at']) ?></span>
              </span>
            </div>
            <div class="mt-1 text-sm text-slate-700 whitespace-pre-wrap break-words"><?= e($c['content']) ?></div>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($canComment): ?>
    <form method="post" action="<?= e($commentAction) ?>" class="px-6 py-4 border-t space-y-3">
      <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
      <textarea name="content" rows="3" required
                placeholder="<?= e($t('comment.placeholder')) ?>"
                class="w-full border rounded px-3 py-2 text-sm"></textarea>
      <button class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">
        <?= e($t('comment.submit')) ?>
      </button>
    </form>
  <?php else: ?>
    <div class="px-6 py-4 border-t text-sm text-slate-500">
      <a href="<?= e(url('admin/login')) ?>" class="text-blue-600 hover:underline"><?= e($t('nav.login')) ?></a>
      <?= e($t('comment.login_required')) ?>
    </div>
  <?php endif; ?>
</section>