<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$invites = \Wiki\Models\Invite::listAll();
$flash = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);
?>

<?php if ($flash): ?>
  <div class="border rounded p-3 mb-4 <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <?= e($flash['text']) ?>
  </div>
<?php endif; ?>

<div class="bg-white rounded shadow mb-6 p-6">
  <h2 class="font-bold mb-4"><?= e($t('admin.invites.new')) ?></h2>
  <form method="post" action="<?= e(url('admin/invites/create')) ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
    <input name="code" placeholder="<?= e($t('admin.invites.code_placeholder')) ?>"
           class="border rounded px-3 py-2 font-mono">
    <input name="max_uses" type="number" min="1" value="1"
           class="border rounded px-3 py-2" placeholder="<?= e($t('admin.invites.uses')) ?>">
    <input name="expires_at" type="date" class="border rounded px-3 py-2">
    <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
      <?= e($t('admin.invites.create')) ?>
    </button>
  </form>
</div>

<div class="bg-white rounded shadow">
  <div class="px-6 py-4 border-b"><h2 class="font-bold"><?= e($t('admin.invites.title')) ?></h2></div>
  <?php if (!$invites): ?>
    <div class="px-6 py-6 text-sm text-slate-500"><?= e($t('admin.invites.empty')) ?></div>
  <?php else: ?>
    <table class="w-full text-sm">
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="px-4 py-2 text-left"><?= e($t('admin.invites.code')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.invites.uses')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.invites.expires')) ?></th>
          <th class="px-4 py-2 text-left"><?= e($t('admin.invites.creator')) ?></th>
          <th class="px-4 py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($invites as $i): ?>
          <tr class="border-t">
            <td class="px-4 py-2 font-mono"><?= e($i['code']) ?></td>
            <td class="px-4 py-2"><?= (int)$i['used_count'] ?> / <?= (int)$i['max_uses'] ?></td>
            <td class="px-4 py-2 text-slate-500"><?= e($i['expires_at'] ?? '-') ?></td>
            <td class="px-4 py-2 text-slate-500"><?= e($i['creator'] ?? '-') ?></td>
            <td class="px-4 py-2 text-right">
              <form method="post" action="<?= e(url('admin/invites/delete/' . (int)$i['id'])) ?>"
                    onsubmit="return confirm('<?= e($t('admin.invites.confirm_delete')) ?>')">
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
