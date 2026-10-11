<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);

$filters = [
    'keyword' => trim((string)($_GET['keyword'] ?? '')),
    'role'    => trim((string)($_GET['role'] ?? '')),
    'status'  => trim((string)($_GET['status'] ?? '')),
];
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(10, min(500, (int)($_GET['per_page'] ?? 50)));

$result = \Wiki\Models\User::paged($filters, $page, $perPage);
$items  = $result['items'];

$selfId = (int)\Wiki\Auth\Auth::user()['id'];

$backParams = array_filter(array_merge($filters, ['per_page' => $perPage, 'page' => $page]));

$qbase = array_filter(array_merge($filters, ['per_page' => $perPage]), fn($v) => $v !== '');
$mkUrl = fn(array $override) => 'admin/users?' . http_build_query(array_merge($qbase, $override));

$roleOptions   = ['user' => 'user', 'editor' => 'editor', 'admin' => 'admin'];
$statusOptions = ['active' => 'active', 'pending' => 'pending', 'banned' => 'banned'];

$flash = $_SESSION['flash_admin'] ?? null;
unset($_SESSION['flash_admin']);

// Refill values for the create form after a validation error
$createData = $_SESSION['flash_create_user'] ?? [];
unset($_SESSION['flash_create_user']);
$showCreateForm = !empty($createData);
?>

<?php if ($flash): ?>
  <div class="border rounded p-3 mb-4 <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <?= e($flash['text']) ?>
  </div>
<?php endif; ?>

<!-- Toolbar -->
<div class="flex items-center gap-3 mb-4">
  <button type="button" id="toggle-create"
          class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm">
    <?= e($showCreateForm ? $t('admin.users.hide_create') : $t('admin.users.show_create')) ?>
  </button>
</div>

<!-- Create user panel -->
<div id="create-panel" class="<?= $showCreateForm ? '' : 'hidden' ?> bg-white rounded shadow mb-4 p-6">
  <h2 class="font-bold mb-4"><?= e($t('admin.users.create_title')) ?></h2>
  <form method="post" action="<?= e(url('admin/users/create')) ?>" class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
    <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
    <?php foreach ($backParams as $k => $v): ?>
      <input type="hidden" name="back[<?= e($k) ?>]" value="<?= e((string)$v) ?>">
    <?php endforeach; ?>

    <label>
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('auth.username')) ?></span>
      <input name="username" required minlength="3" maxlength="64"
             value="<?= e((string)($createData['username'] ?? '')) ?>"
             pattern="[A-Za-z0-9_\-\.]{3,64}"
             class="w-full border rounded px-3 py-2">
      <span class="text-xs text-slate-400"><?= e($t('auth.username_hint')) ?></span>
    </label>

    <label>
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('auth.email')) ?></span>
      <input name="email" type="email" required
             value="<?= e((string)($createData['email'] ?? '')) ?>"
             class="w-full border rounded px-3 py-2">
    </label>

    <label>
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('auth.password')) ?></span>
      <input name="password" type="password" required minlength="6"
             class="w-full border rounded px-3 py-2">
      <span class="text-xs text-slate-400"><?= e($t('auth.password_hint')) ?></span>
    </label>

    <label>
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('profile.display_name')) ?></span>
      <input name="display_name" value="<?= e((string)($createData['display_name'] ?? '')) ?>"
             class="w-full border rounded px-3 py-2">
    </label>

    <label>
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.users.role')) ?></span>
      <select name="role" class="w-full border rounded px-3 py-2">
        <?php foreach ($roleOptions as $v): ?>
          <option value="<?= e($v) ?>" <?= ($createData['role'] ?? 'user') === $v ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.users.status')) ?></span>
      <select name="status" class="w-full border rounded px-3 py-2">
        <?php foreach ($statusOptions as $v): ?>
          <option value="<?= e($v) ?>" <?= ($createData['status'] ?? 'active') === $v ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="flex items-center gap-2 md:col-span-2">
      <input type="checkbox" name="email_verified" value="1"
             <?= (($createData['email_verified'] ?? 1) ? 'checked' : '') ?>>
      <span><?= e($t('admin.users.verified')) ?></span>
    </label>

    <div class="md:col-span-2 flex items-center gap-3">
      <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
        <?= e($t('admin.users.create_submit')) ?>
      </button>
      <button type="button" id="cancel-create"
              class="text-slate-500 px-4 py-2 hover:underline">
        <?= e($t('page.cancel')) ?>
      </button>
    </div>
  </form>
</div>

<!-- Filter bar -->
<div class="bg-white rounded shadow mb-4 p-4">
  <form method="get" action="<?= e(url('admin')) ?>" class="grid grid-cols-1 md:grid-cols-12 gap-3 text-sm">
    <input type="hidden" name="p" value="users">

    <label class="md:col-span-5">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.users.filter_keyword')) ?></span>
      <input name="keyword" value="<?= e($filters['keyword']) ?>"
             placeholder="<?= e($t('admin.users.filter_keyword_hint')) ?>"
             class="w-full border rounded px-3 py-1.5">
    </label>

    <label class="md:col-span-2">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.users.filter_role')) ?></span>
      <select name="role" class="w-full border rounded px-2 py-1.5">
        <option value=""><?= e($t('admin.users.filter_any')) ?></option>
        <?php foreach ($roleOptions as $v): ?>
          <option value="<?= e($v) ?>" <?= $filters['role'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="md:col-span-2">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.users.filter_status')) ?></span>
      <select name="status" class="w-full border rounded px-2 py-1.5">
        <option value=""><?= e($t('admin.users.filter_any')) ?></option>
        <?php foreach ($statusOptions as $v): ?>
          <option value="<?= e($v) ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="md:col-span-1">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.users.per_page')) ?></span>
      <select name="per_page" class="w-full border rounded px-2 py-1.5">
        <?php foreach ([20, 50, 100, 200, 500] as $n): ?>
          <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="md:col-span-2 flex items-end gap-2">
      <button class="bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700">
        <?= e($t('admin.users.filter_apply')) ?>
      </button>
      <a href="<?= e(url('admin/users')) ?>" class="text-slate-500 px-3 py-1.5 hover:underline">
        <?= e($t('admin.users.filter_reset')) ?>
      </a>
    </div>
  </form>
</div>

<!-- Batch toolbar -->
<div class="bg-white rounded shadow mb-2 px-6 py-3 flex items-center gap-3 flex-wrap text-sm">
  <label class="flex items-center gap-1.5">
    <input type="checkbox" id="select-all">
    <span><?= e($t('admin.users.batch_select_all')) ?></span>
  </label>

  <span id="selected-count" class="text-xs text-slate-500">
    <?= e($t('admin.users.batch_selected', ['n' => 0])) ?>
  </span>

  <form method="post" action="<?= e(url('admin/users/batch')) ?>" id="batch-form"
        class="ml-auto flex items-center gap-2 flex-wrap">
    <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
    <?php foreach ($backParams as $k => $v): ?>
      <input type="hidden" name="back[<?= e($k) ?>]" value="<?= e((string)$v) ?>">
    <?php endforeach; ?>
    <div id="batch-ids"></div>

    <select name="role" class="border rounded px-2 py-1 text-xs">
      <option value=""><?= e($t('admin.users.batch_role_placeholder')) ?></option>
      <?php foreach ($roleOptions as $v): ?>
        <option value="<?= e($v) ?>"><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" name="op" value="set_role"
            class="text-xs border rounded px-3 py-1 hover:bg-slate-50">
      <?= e($t('admin.users.batch_set_role')) ?>
    </button>

    <select name="status" class="border rounded px-2 py-1 text-xs">
      <option value=""><?= e($t('admin.users.batch_status_placeholder')) ?></option>
      <?php foreach ($statusOptions as $v): ?>
        <option value="<?= e($v) ?>"><?= e($v) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" name="op" value="set_status"
            class="text-xs border rounded px-3 py-1 hover:bg-slate-50">
      <?= e($t('admin.users.batch_set_status')) ?>
    </button>

    <button type="submit" name="op" value="verify"
            class="text-xs border rounded px-3 py-1 hover:bg-slate-50">
      <?= e($t('admin.users.batch_verify')) ?>
    </button>

    <button type="submit" name="op" value="unverify"
            class="text-xs border rounded px-3 py-1 hover:bg-slate-50">
      <?= e($t('admin.users.batch_unverify')) ?>
    </button>

    <button type="submit" name="op" value="delete"
            class="text-xs text-red-600 border border-red-300 rounded px-3 py-1 hover:bg-red-50"
            onclick="return confirm('<?= e($t('admin.users.confirm_batch_delete')) ?>')">
      <?= e($t('admin.users.batch_delete')) ?>
    </button>
  </form>
</div>

<!-- Table -->
<div class="bg-white rounded shadow">
  <?php if (!$items): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('admin.users.no_results')) ?></div>
  <?php else: ?>
    <table class="w-full text-sm table-fixed">
      <colgroup>
        <col class="w-8">
        <col class="w-12">
        <col class="w-40">
        <col>
        <col class="w-24">
        <col class="w-24">
        <col class="w-16">
        <col class="w-32">
        <col class="w-24">
      </colgroup>
      <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
        <tr>
          <th class="px-3 py-2"></th>
          <th class="px-3 py-2 text-left">ID</th>
          <th class="px-3 py-2 text-left"><?= e($t('auth.username')) ?></th>
          <th class="px-3 py-2 text-left"><?= e($t('auth.email')) ?></th>
          <th class="px-3 py-2 text-left"><?= e($t('admin.users.role')) ?></th>
          <th class="px-3 py-2 text-left"><?= e($t('admin.users.status')) ?></th>
          <th class="px-3 py-2 text-left"><?= e($t('admin.users.verified')) ?></th>
          <th class="px-3 py-2 text-left"><?= e($t('admin.users.created')) ?></th>
          <th class="px-3 py-2"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $u):
            $isSelf = (int)$u['id'] === $selfId;
            $rowFormId = 'user-form-' . (int)$u['id'];
        ?>
          <tr class="border-t <?= $isSelf ? 'bg-blue-50/30' : '' ?>">
            <td class="px-3 py-2">
              <?php if (!$isSelf): ?>
                <input type="checkbox" name="ids[]" value="<?= (int)$u['id'] ?>" class="row-checkbox" form="batch-form">
              <?php else: ?>
                <span class="text-xs text-slate-400">—</span>
              <?php endif; ?>
            </td>
            <td class="px-3 py-2 text-slate-400"><?= (int)$u['id'] ?></td>
            <td class="px-3 py-2 font-medium truncate" title="<?= e($u['username']) ?>">
              <?= e($u['username']) ?>
              <?php if ($isSelf): ?>
                <span class="text-xs bg-blue-100 text-blue-700 px-1.5 rounded ml-1"><?= e($t('admin.users.you')) ?></span>
              <?php endif; ?>
            </td>
            <td class="px-3 py-2 text-slate-500 truncate" title="<?= e($u['email']) ?>"><?= e($u['email']) ?></td>

            <?php if ($isSelf): ?>
              <td colspan="5" class="px-3 py-2 text-xs text-slate-400">
                <?= e($t('admin.users.self_readonly')) ?>
              </td>
            <?php else: ?>
              <form id="<?= e($rowFormId) ?>" method="post"
                    action="<?= e(url('admin/users/update/' . (int)$u['id'])) ?>">
                <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
                <?php foreach ($backParams as $k => $v): ?>
                  <input type="hidden" name="back[<?= e($k) ?>]" value="<?= e((string)$v) ?>">
                <?php endforeach; ?>
              </form>
              <td class="px-3 py-2">
                <select name="role" form="<?= e($rowFormId) ?>" class="w-full border rounded px-2 py-1 text-xs">
                  <?php foreach ($roleOptions as $v): ?>
                    <option value="<?= e($v) ?>" <?= $u['role'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="px-3 py-2">
                <select name="status" form="<?= e($rowFormId) ?>" class="w-full border rounded px-2 py-1 text-xs">
                  <?php foreach ($statusOptions as $v): ?>
                    <option value="<?= e($v) ?>" <?= $u['status'] === $v ? 'selected' : '' ?>><?= e($v) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td class="px-3 py-2 text-center">
                <input type="checkbox" name="email_verified" value="1"
                       form="<?= e($rowFormId) ?>"
                       <?= (int)$u['email_verified'] === 1 ? 'checked' : '' ?>>
              </td>
              <td class="px-3 py-2 text-slate-400 text-xs whitespace-nowrap"><?= e($u['created_at']) ?></td>
              <td class="px-3 py-2 text-right whitespace-nowrap">
                <button type="submit" form="<?= e($rowFormId) ?>"
                        class="text-xs bg-slate-700 text-white px-2 py-1 rounded hover:bg-slate-800">
                  <?= e($t('admin.users.save')) ?>
                </button>
                <button type="button"
                        onclick="deleteUser(<?= (int)$u['id'] ?>, '<?= e($t('admin.users.confirm_delete')) ?>')"
                        class="text-xs text-red-600 hover:underline ml-1">
                  <?= e($t('admin.users.delete')) ?>
                </button>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($result['pages'] > 1): ?>
      <div class="px-6 py-4 border-t flex items-center gap-2 flex-wrap text-sm">
        <?php if ($page > 1): ?>
          <a href="<?= e($mkUrl(['page' => 1])) ?>" class="px-2 py-1 border rounded hover:bg-slate-100">«</a>
          <a href="<?= e($mkUrl(['page' => $page - 1])) ?>" class="px-2 py-1 border rounded hover:bg-slate-100">‹</a>
        <?php endif; ?>
        <?php
        $start = max(1, $page - 2);
        $end   = min($result['pages'], $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
          <a href="<?= e($mkUrl(['page' => $i])) ?>"
             class="px-3 py-1 border rounded <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-slate-100' ?>">
            <?= $i ?>
          </a>
        <?php endfor; ?>
        <?php if ($page < $result['pages']): ?>
          <a href="<?= e($mkUrl(['page' => $page + 1])) ?>" class="px-2 py-1 border rounded hover:bg-slate-100">›</a>
          <a href="<?= e($mkUrl(['page' => $result['pages']])) ?>" class="px-2 py-1 border rounded hover:bg-slate-100">»</a>
        <?php endif; ?>
        <span class="ml-auto text-xs text-slate-500">
          <?= e($t('admin.users.page_of', ['page' => $page, 'pages' => $result['pages'], 'total' => $result['total']])) ?>
        </span>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<!-- Hidden single-delete form -->
<form id="single-delete-form" method="post" action="<?= e(url('admin/users/delete')) ?>" class="hidden">
  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
  <?php foreach ($backParams as $k => $v): ?>
    <input type="hidden" name="back[<?= e($k) ?>]" value="<?= e((string)$v) ?>">
  <?php endforeach; ?>
  <input type="hidden" name="id" id="single-delete-id">
</form>

<script>
(function () {
  // ---- Batch selection ----
  const selectAll = document.getElementById('select-all');
  const counter   = document.getElementById('selected-count');
  const batchIds  = document.getElementById('batch-ids');
  const checks    = () => document.querySelectorAll('.row-checkbox');

  function syncBatchIds() {
    // The row checkboxes are associated with the batch form via the form attribute,
    // so they submit directly. Just update the counter and select-all state here.
    const all = checks();
    const n = Array.from(all).filter(c => c.checked).length;

    counter.textContent = <?= json_encode($t('admin.users.batch_selected', ['n' => 0])) ?>.replace('{n}', n);

    if (all.length > 0) {
      selectAll.checked = n === all.length;
      selectAll.indeterminate = n > 0 && n < all.length;
    }
  }

  selectAll?.addEventListener('change', function () {
    checks().forEach(c => { c.checked = selectAll.checked; });
    syncBatchIds();
  });

  document.addEventListener('change', function (e) {
    if (e.target && e.target.classList.contains('row-checkbox')) syncBatchIds();
  });

  syncBatchIds();

  // ---- Create panel toggle ----
  const panel       = document.getElementById('create-panel');
  const toggleBtn   = document.getElementById('toggle-create');
  const cancelBtn   = document.getElementById('cancel-create');

  toggleBtn?.addEventListener('click', function () {
    panel.classList.toggle('hidden');
    if (!panel.classList.contains('hidden')) {
      panel.querySelector('input[name="username"]')?.focus();
    }
  });
  cancelBtn?.addEventListener('click', function () {
    panel.classList.add('hidden');
  });
})();

function deleteUser(id, msg) {
  if (!confirm(msg)) return false;
  const f = document.getElementById('single-delete-form');
  document.getElementById('single-delete-id').value = id;

  // Remove any ids[] fields that accidentally belong to the batch form on this row
  // (they are associated via form="batch-form" so they do not interfere here)
  f.submit();
  return false;
}
</script>
