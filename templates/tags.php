<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = '';
ob_start();
?>
<div class="bg-white rounded shadow p-6">
  <h1 class="text-xl font-bold mb-4"><?= e($t('tag.title')) ?></h1>
  <?php if (!$tags): ?>
    <p class="text-sm text-slate-500"><?= e($t('tag.empty')) ?></p>
  <?php else: ?>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($tags as $tag): ?>
        <a href="<?= e(url('tag/' . $tag['slug'])) ?>"
           class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-slate-100 text-slate-700 hover:bg-blue-100 hover:text-blue-700 text-sm">
          <?= e($tag['name']) ?>
          <span class="text-xs text-slate-400"><?= (int)$tag['usage_count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';