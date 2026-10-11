<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = '';
ob_start();
?>
<div class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b">
    <h1 class="text-xl font-bold"><?= e($t('search.title')) ?></h1>
    <form method="get" action="<?= e(url('search')) ?>" class="mt-3 flex gap-2">
      <input name="q" value="<?= e($q) ?>" autofocus
             placeholder="<?= e($t('search.placeholder')) ?>"
             class="flex-1 border rounded px-3 py-2">
      <?php if ($lang !== ''): ?>
        <input type="hidden" name="lang" value="<?= e($lang) ?>">
      <?php endif; ?>
      <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        <?= e($t('search.submit')) ?>
      </button>
    </form>
  </header>

  <?php if ($q === ''): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('search.empty_query')) ?></div>
  <?php elseif (!$results): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('search.no_results', ['q' => $q])) ?></div>
  <?php else: ?>
    <div class="px-6 py-3 border-b bg-slate-50 text-xs text-slate-500">
      <?= e($t('search.result_count', ['n' => count($results), 'q' => $q])) ?>
    </div>
    <ul class="divide-y divide-slate-100">
      <?php foreach ($results as $r):
        $href = url('page/' . $r['slug'] . ($r['lang'] === (string)\Wiki\Models\Settings::get('site.default_lang') ? '' : '/' . $r['lang']));
      ?>
        <li class="px-6 py-4">
          <a href="<?= e($href) ?>" class="font-medium text-blue-700 hover:underline">
            <?= e($r['title']) ?>
          </a>
          <span class="text-xs text-slate-400 ml-2"><?= e($r['lang']) ?></span>
          <?php if (!empty($r['snippet'])): ?>
            <div class="mt-1 text-sm text-slate-600 line-clamp-3"><?= $r['snippet'] ?></div>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';