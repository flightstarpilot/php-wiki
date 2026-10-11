<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = '';
ob_start();
?>
<div class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b flex items-center gap-2">
    <span class="text-slate-400"><?= icon('file', 'w-5 h-5') ?></span>
    <h1 class="text-xl font-bold"><?= e($tag['name']) ?></h1>
    <span class="text-sm text-slate-400">(<?= count($pages) ?>)</span>
    <a href="<?= e(url('tags')) ?>" class="ml-auto text-sm text-slate-500 hover:underline">
      <?= e($t('tag.all')) ?>
    </a>
  </header>

  <?php if (!$pages): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('tag.no_pages')) ?></div>
  <?php else: ?>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($pages as $p):
        $href = url('page/' . $p['group_slug'] . ($p['lang'] === $p['default_lang'] ? '' : '/' . $p['lang']));
      ?>
        <li class="px-6 py-3 flex items-center gap-3">
          <a href="<?= e($href) ?>" class="font-medium hover:text-blue-600 flex-1">
            <?= e($p['title']) ?>
          </a>
          <span class="text-xs text-slate-400"><?= e($p['lang']) ?></span>
          <span class="text-xs text-slate-400"><?= e($p['updated_at']) ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';