<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$items = \Wiki\Models\Redirect::listAll(500, 0);
$flash = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);
?>

<?php if ($flash): ?>
  <div class="border rounded p-3 mb-4 <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <?= e($flash['text']) ?>
  </div>
<?php endif; ?>

<div class="bg-white rounded shadow mb-6 p-6">
  <h2 class="font-bold mb-4"><?= e($t('admin.redirects.new')) ?></h2>
  <form method="post" action="<?= e(url('admin/redirects/create')) ?>"
        class="grid grid-cols-1 md:grid-cols-5 gap-3">
    <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
    <input name="from_path" placeholder="/page/old-slug"
           class="border rounded px-3 py-2 font-mono text-sm md:col-span-2" required>
    <input name="to_path" placeholder="/page/new-slug"
           class="border rounded px-3 py-2 font-mono text-sm md:col-span-2" required>
    <select name="status_code" class="border rounded px-3 py-2 text-sm">
      <option value="301">301</option>
      <option value="302">302</option>
      <option value="307">307</option>
      <option value="308">308</option>
    </select>
    <button class="md:col-span-5 bg-blue-600 text-white px-4 py-2 rounded justify-self-start hover:bg-blue-700">
      <?= e($t('admin.redirects.create')) ?>
    </button>
  </form>
</div>

<div class="bg-white rounded shadow">
  <div class="px-6 py-4 border-b flex items-center gap-3">
    <h2 class="font-bold flex-1"><?= e($t('admin.redirects.title')) ?></h2>
    <span class="text-xs text-slate-500"><?= count($items) ?></span>
  </div>
  <?php if (!$items): ?>
    <div class="px-6 py-6 text-sm text-slate-500"><?= e($t('admin.redirects.empty')) ?></div>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="px-4 py-2 text-left"><?= e($t('admin.redirects.from')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.redirects.to')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.redirects.code')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.redirects.hits')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.redirects.creator')) ?></th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $r): ?>
          <tr class="border-t">
            <td class="px-4 py-2 font-mono text-xs"><?= e($r['from_path']) ?></td>
            <td class="px-4 py-2 font-mono text-xs"><?= e($r['to_path']) ?></td>
            <td class="px-4 py-2"><?= (int)$r['status_code'] ?></td>
            <td class="px-4 py-2"><?= (int)$r['hits'] ?></td>
            <td class="px-4 py-2 text-slate-500 text-xs"><?= e($r['creator'] ?? '-') ?></td>
            <td class="px-4 py-2 text-right">
              <form method="post" action="<?= e(url('admin/redirects/delete/' . (int)$r['id'])) ?>"
                    onsubmit="return confirm('<?= e($t('admin.redirects.confirm_delete')) ?>')">
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
