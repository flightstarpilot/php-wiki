<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = '';
ob_start();
?>
<div class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b flex items-center gap-3 flex-wrap">
    <h1 class="text-xl font-bold flex-1"><?= e($t('issue.title')) ?></h1>
    <?php if (\Wiki\Auth\Auth::check()): ?>
      <a href="<?= e(url('issues/new')) ?>" class="flex items-center gap-1 px-3 py-1.5 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
        <?= icon('plus', 'w-4 h-4') ?><span><?= e($t('issue.new_title')) ?></span>
      </a>
    <?php endif; ?>
  </header>

  <div class="px-6 py-2 border-b bg-slate-50 flex items-center gap-2 text-sm">
    <?php foreach (['open' => 'issue.filter_open', 'closed' => 'issue.filter_closed', 'all' => 'issue.filter_all'] as $k => $labelKey): ?>
      <a href="<?= e(url('issues?status=' . $k)) ?>"
         class="px-3 py-1 rounded <?= $status === $k ? 'bg-blue-100 text-blue-700' : 'text-slate-500 hover:bg-slate-100' ?>">
        <?= e($t($labelKey)) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$issues): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('issue.empty')) ?></div>
  <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($issues as $i): ?>
        <li class="px-6 py-3 flex items-start gap-3">
          <span class="mt-1 <?= $i['status'] === 'open' ? 'text-green-600' : 'text-slate-400' ?>">
            <?= icon('alert', 'w-4 h-4') ?>
          </span>
          <div class="flex-1 min-w-0">
            <a href="<?= e(url('issue/' . (int)$i['id'])) ?>" class="font-medium hover:text-blue-600">
              <?= e($i['title']) ?>
            </a>
            <div class="text-xs text-slate-400 mt-1 flex items-center gap-3 flex-wrap">
              <span><?= e($t('issue.number', ['id' => (int)$i['id']])) ?></span>
              <span><?= e($t('issue.by', ['user' => $i['author'] ?? '-'])) ?></span>
              <span><?= e($i['updated_at']) ?></span>
              <?php if (!empty($i['group_slug'])): ?>
                <a href="<?= e(url('page/' . $i['group_slug'])) ?>" class="text-slate-500 hover:underline">
                  <?= e($i['group_slug']) ?>
                </a>
              <?php endif; ?>
            </div>
          </div>
          <span class="text-xs px-2 py-0.5 rounded <?= $i['status'] === 'open' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500' ?>">
            <?= e($t('issue.status.' . $i['status'])) ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';