<?php
$currentSlug = $group['slug'];
$defaultLang = (string)\Wiki\Models\Settings::get('site.default_lang');
$pageTitle   = \Wiki\Services\I18nService::t('page.history_title', ['title' => $page['title'] ?? '']);
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$canEdit = \Wiki\Auth\Auth::isEditor();
$viewUrl = url('page/' . $group['slug'] . ($page['lang'] === $defaultLang ? '' : '/' . $page['lang']));
$diffBase = url('page/' . $group['slug'] . '/' . $page['lang'] . '/diff');

ob_start();
?>
<div class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b flex items-center gap-3 flex-wrap">
    <a href="<?= e($viewUrl) ?>" class="flex items-center gap-1 text-slate-500 hover:text-slate-900">
      <?= icon('arrow-left', 'w-4 h-4') ?><span><?= e($t('page.back')) ?></span>
    </a>
    <h1 class="text-xl font-bold flex-1">
      <?= e($t('page.history_title', ['title' => $page['title'] ?? $group['slug']])) ?>
    </h1>
  </header>

  <?php if ($versions): ?>
    <form method="get" action="<?= e($diffBase) ?>" class="px-6 py-3 border-b bg-slate-50 flex items-center gap-2 text-sm flex-wrap">
      <span class="text-slate-500"><?= e($t('page.compare')) ?></span>
      <select name="v1" class="border rounded px-2 py-1 bg-white">
        <?php foreach ($versions as $v): ?>
          <option value="<?= (int)$v['version'] ?>">v<?= (int)$v['version'] ?></option>
        <?php endforeach; ?>
      </select>
      <span class="text-slate-400">&rarr;</span>
      <select name="v2" class="border rounded px-2 py-1 bg-white">
        <?php foreach ($versions as $v): ?>
          <option value="<?= (int)$v['version'] ?>" <?= $v === $versions[0] ? 'selected' : '' ?>>v<?= (int)$v['version'] ?></option>
        <?php endforeach; ?>
      </select>
      <button class="flex items-center gap-1 px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
        <?= icon('compare', 'w-4 h-4') ?><span><?= e($t('page.view_diff')) ?></span>
      </button>
    </form>
  <?php endif; ?>

  <?php if (!$versions): ?>
    <div class="px-6 py-8 text-slate-500"><?= e($t('page.no_versions')) ?></div>
  <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($versions as $i => $v): ?>
        <li class="px-6 py-3 flex items-center gap-3 flex-wrap">
          <div class="flex items-center gap-2 min-w-[110px]">
            <span class="font-mono font-medium">v<?= (int)$v['version'] ?></span>
            <?php if ($i === 0): ?>
              <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded"><?= e($t('page.current')) ?></span>
            <?php endif; ?>
          </div>

          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate"><?= e($v['title']) ?></div>
            <?php if (!empty($v['comment'])): ?>
              <div class="text-xs text-slate-500 truncate"><?= e($v['comment']) ?></div>
            <?php endif; ?>
          </div>

          <div class="text-xs text-slate-400 flex items-center gap-1">
            <?= icon('user', 'w-3 h-3') ?><span><?= e($v['author'] ?? '-') ?></span>
          </div>

          <div class="text-xs text-slate-400 flex items-center gap-1 min-w-[150px]">
            <?= icon('clock', 'w-3 h-3') ?><span><?= e($v['created_at']) ?></span>
          </div>

          <div class="flex items-center gap-1">
            <?php if ($i > 0): ?>
              <a href="<?= e($diffBase . '?v1=' . (int)$v['version'] . '&v2=' . (int)$versions[0]['version']) ?>"
                 class="text-xs px-2 py-1 rounded text-slate-500 hover:bg-slate-100 flex items-center gap-1">
                <?= icon('compare', 'w-3 h-3') ?><span><?= e($t('page.diff_with_current')) ?></span>
              </a>
              <?php if ($canEdit): ?>
                <form method="post" action="<?= e(url('page/rollback')) ?>" class="inline"
                      onsubmit="return confirm('<?= e($t('page.confirm_rollback', ['v' => (int)$v['version']])) ?>')">
                  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
                  <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">
                  <input type="hidden" name="version" value="<?= (int)$v['version'] ?>">
                  <button class="text-xs px-2 py-1 rounded text-slate-500 hover:bg-slate-100 flex items-center gap-1">
                    <?= icon('undo', 'w-3 h-3') ?><span><?= e($t('page.rollback')) ?></span>
                  </button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';