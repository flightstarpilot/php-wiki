<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = '';
ob_start();
?>
<div class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b flex items-center gap-3 flex-wrap">
    <h1 class="text-xl font-bold flex-1"><?= e($t('proposal.title')) ?></h1>
  </header>

  <div class="px-6 py-2 border-b bg-slate-50 flex items-center gap-2 text-sm">
    <?php foreach (['open' => 'proposal.filter_open', 'merged' => 'proposal.filter_merged', 'closed' => 'proposal.filter_closed', 'all' => 'proposal.filter_all'] as $k => $labelKey): ?>
      <a href="<?= e(url('proposals?status=' . $k)) ?>"
         class="px-3 py-1 rounded <?= $status === $k ? 'bg-blue-100 text-blue-700' : 'text-slate-500 hover:bg-slate-100' ?>">
        <?= e($t($labelKey)) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$proposals): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('proposal.empty')) ?></div>
  <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($proposals as $p): ?>
        <li class="px-6 py-3 flex items-start gap-3">
          <span class="mt-1 text-slate-400"><?= icon('compare', 'w-4 h-4') ?></span>
          <div class="flex-1 min-w-0">
            <a href="<?= e(url('proposal/' . (int)$p['id'])) ?>" class="font-medium hover:text-blue-600">
              <?= e($p['title']) ?>
            </a>
            <div class="text-xs text-slate-400 mt-1 flex items-center gap-3 flex-wrap">
              <span><?= e($t('proposal.number', ['id' => (int)$p['id']])) ?></span>
              <span><?= e($t('proposal.by', ['user' => $p['author'] ?? '-'])) ?></span>
              <span><?= e($p['updated_at']) ?></span>
              <?php if (!empty($p['group_slug'])): ?>
                <a href="<?= e(url('page/' . $p['group_slug'])) ?>" class="text-slate-500 hover:underline">
                  <?= e($p['group_slug']) ?>
                </a>
              <?php endif; ?>
            </div>
          </div>
          <span class="text-xs px-2 py-0.5 rounded
            <?= $p['status'] === 'open' ? 'bg-green-100 text-green-700' : '' ?>
            <?= $p['status'] === 'merged' ? 'bg-purple-100 text-purple-700' : '' ?>
            <?= $p['status'] === 'closed' ? 'bg-slate-100 text-slate-500' : '' ?>">
            <?= e($t('proposal.status.' . $p['status'])) ?>
          </span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';