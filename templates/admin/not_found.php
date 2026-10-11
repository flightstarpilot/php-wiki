<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$items = \Wiki\Models\NotFoundLog::listAll(300, 0);
?>

<div class="bg-white rounded shadow">
  <div class="px-6 py-4 border-b flex items-center gap-3">
    <h2 class="font-bold flex-1"><?= e($t('admin.not_found.title')) ?></h2>
    <form method="post" action="<?= e(url('admin/not_found/clear')) ?>"
          onsubmit="return confirm('<?= e($t('admin.not_found.confirm_clear')) ?>')">
      <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
      <button class="text-xs text-red-600 hover:underline"><?= e($t('admin.not_found.clear')) ?></button>
    </form>
  </div>

  <?php if (!$items): ?>
    <div class="px-6 py-6 text-sm text-slate-500"><?= e($t('admin.not_found.empty')) ?></div>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="px-4 py-2 text-left"><?= e($t('admin.not_found.path')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.not_found.hits')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.not_found.last')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.not_found.referer')) ?></th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $r): ?>
          <tr class="border-t">
            <td class="px-4 py-2 font-mono text-xs break-all"><?= e($r['path']) ?></td>
            <td class="px-4 py-2"><?= (int)$r['hits'] ?></td>
            <td class="px-4 py-2 text-xs text-slate-500 whitespace-nowrap"><?= e($r['last_seen']) ?></td>
            <td class="px-4 py-2 text-xs text-slate-400 truncate max-w-xs"><?= e($r['referer']) ?></td>
            <td class="px-4 py-2 text-right">
              <form method="post" action="<?= e(url('admin/redirects/create')) ?>" class="inline">
                <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
                <input type="hidden" name="from_path" value="<?= e($r['path']) ?>">
                <input type="hidden" name="to_path" value="/page/">
                <input type="hidden" name="status_code" value="301">
                <button class="text-xs text-blue-600 hover:underline"><?= e($t('admin.not_found.create_redirect')) ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
