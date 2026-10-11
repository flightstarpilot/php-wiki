<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = '';
$allGroups = \Wiki\Models\TranslationGroup::all();
$titles = \Wiki\Models\Page::titlesByGroup();

ob_start();
?>
<form method="post" action="<?= e(url('issues')) ?>" class="bg-white rounded shadow max-w-2xl">
  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">

  <header class="px-6 py-4 border-b">
    <h1 class="text-xl font-bold"><?= e($t('issue.new_title')) ?></h1>
  </header>

  <div class="px-6 py-4 space-y-4">
    <label class="block">
      <span class="text-sm text-slate-600"><?= e($t('issue.related_page')) ?></span>
      <select name="group_id" class="mt-1 w-full border rounded px-3 py-2" required>
        <option value=""><?= e($t('issue.select_page')) ?></option>
        <?php foreach ($allGroups as $g):
          $title = $titles[$g['id']][$g['default_lang']] ?? $g['slug'];
        ?>
          <option value="<?= (int)$g['id'] ?>" <?= ($group && (int)$group['id'] === (int)$g['id']) ? 'selected' : '' ?>>
            <?= e($title) ?> (<?= e($g['slug']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="block">
      <span class="text-sm text-slate-600"><?= e($t('issue.field_title')) ?></span>
      <input name="title" required class="mt-1 w-full border rounded px-3 py-2">
    </label>

    <label class="block">
      <span class="text-sm text-slate-600"><?= e($t('issue.field_content')) ?></span>
      <textarea name="content" rows="6" class="mt-1 w-full border rounded px-3 py-2"></textarea>
    </label>
  </div>

  <div class="px-6 py-4 border-t flex gap-3">
    <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= e($t('issue.submit')) ?></button>
    <a href="<?= e(url('issues')) ?>" class="text-slate-500 px-4 py-2 rounded hover:bg-slate-100"><?= e($t('page.cancel')) ?></a>
  </div>
</form>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';