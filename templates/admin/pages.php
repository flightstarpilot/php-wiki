<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$groups = \Wiki\Models\TranslationGroup::listAllWithStats(500, 0);
$flash = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);
?>

<?php if ($flash): ?>
  <div class="border rounded p-3 mb-4 <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <?= e($flash['text']) ?>
  </div>
<?php endif; ?>

<div class="bg-white rounded shadow">
  <div class="px-6 py-4 border-b flex items-center gap-3">
    <h2 class="font-bold flex-1"><?= e($t('admin.pages.title')) ?></h2>
    <span class="text-xs text-slate-500"><?= count($groups) ?></span>
    <a href="<?= e(url('new')) ?>" class="text-sm bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700">
      <?= e($t('admin.pages.new')) ?>
    </a>
  </div>

  <?php if (!$groups): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('admin.pages.empty')) ?></div>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="px-4 py-2 text-left"><?= e($t('admin.pages.slug')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.pages.parent')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.pages.langs')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.pages.updated')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.pages.locked')) ?></th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($groups as $g): ?>
          <tr class="border-t align-middle">
            <td class="px-4 py-2">
              <a href="<?= e(url('page/' . $g['slug'])) ?>" class="font-mono text-xs text-blue-600 hover:underline">
                <?= e($g['slug']) ?>
              </a>
              <div class="text-[11px] text-slate-400">
                <?= e($t('admin.pages.default_lang', ['lang' => $g['default_lang']])) ?>
              </div>
            </td>
            <td class="px-4 py-2">
              <form method="post" action="<?= e(url('admin/pages/move/' . (int)$g['id'])) ?>"
                    class="flex items-center gap-1">
                <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
                <select name="parent_id" class="border rounded px-2 py-1 text-xs max-w-[140px]">
                  <option value=""><?= e($t('admin.pages.no_parent')) ?></option>
                  <?php foreach ($groups as $other): if ((int)$other['id'] === (int)$g['id']) continue; ?>
                    <option value="<?= (int)$other['id'] ?>" <?= (int)$g['parent_id'] === (int)$other['id'] ? 'selected' : '' ?>>
                      <?= e($other['slug']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <input type="number" name="sort_order" value="<?= (int)$g['sort_order'] ?>"
                       class="border rounded px-2 py-1 text-xs w-16">
                <button class="text-xs text-slate-600 hover:underline"><?= e($t('admin.pages.save_move')) ?></button>
              </form>
            </td>
            <td class="px-4 py-2">
              <?php
              $langs = \Wiki\Models\Page::listByGroup((int)$g['id']);
              foreach ($langs as $l):
                $href = url('page/' . $g['slug'] . ($l['lang'] === $g['default_lang'] ? '' : '/' . $l['lang']));
              ?>
                <a href="<?= e($href) ?>" class="inline-block text-xs bg-slate-100 px-1.5 py-0.5 rounded mr-1 hover:bg-blue-100">
                  <?= e($l['lang']) ?>
                </a>
              <?php endforeach; ?>
            </td>
            <td class="px-4 py-2 text-xs text-slate-500"><?= e($g['last_update'] ?? '-') ?></td>
            <td class="px-4 py-2">
              <form method="post" action="<?= e(url('admin/pages/lock/' . (int)$g['id'])) ?>">
                <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
                <input type="hidden" name="locked" value="<?= (int)$g['is_locked'] === 1 ? '0' : '1' ?>">
                <button class="text-xs <?= (int)$g['is_locked'] === 1 ? 'text-amber-600' : 'text-slate-400 hover:underline' ?>">
                  <?= (int)$g['is_locked'] === 1 ? e($t('admin.pages.locked_yes')) : e($t('admin.pages.lock')) ?>
                </button>
              </form>
            </td>
            <td class="px-4 py-2 text-right">
              <form method="post" action="<?= e(url('admin/pages/delete/' . (int)$g['id'])) ?>"
                    onsubmit="return confirm('<?= e($t('admin.pages.confirm_delete')) ?>')">
                <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
                <button class="text-xs text-red-600 hover:underline"><?= e($t('admin.users.delete')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
