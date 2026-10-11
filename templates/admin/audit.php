<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);

$filters = [
    'action'      => trim((string)($_GET['action'] ?? '')),
    'user_id'     => trim((string)($_GET['user_id'] ?? '')),
    'target_type' => trim((string)($_GET['target_type'] ?? '')),
    'keyword'     => trim((string)($_GET['keyword'] ?? '')),
    'from'        => trim((string)($_GET['from'] ?? '')),
    'to'          => trim((string)($_GET['to'] ?? '')),
];
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = max(10, min(500, (int)($_GET['per_page'] ?? 50)));

$result = \Wiki\Models\AuditLog::paged($filters, $page, $perPage);
$items  = $result['items'];

// Build the query string preserving current filters
$qbase = array_filter(array_merge($filters, ['per_page' => $perPage]), fn($v) => $v !== '' && $v !== null);
$mkUrl = function (array $override) use ($qbase) {
    return 'admin/audit?' . http_build_query(array_merge($qbase, $override));
};

// Distinct action prefixes for the dropdown
$actionPrefixes = ['auth', 'admin', 'page', 'issue', 'proposal', 'user', 'profile'];
?>

<?php if (!empty($_GET['cleared'])): ?>
  <div class="bg-green-50 text-green-700 border border-green-200 rounded p-3 mb-4">
    <?= e($t('admin.audit.cleared')) ?>
  </div>
<?php endif; ?>

<div class="bg-white rounded shadow mb-4 p-4">
  <form method="get" action="<?= e(url('admin')) ?>" class="grid grid-cols-1 md:grid-cols-12 gap-3 text-sm">
    <input type="hidden" name="p" value="audit">

    <label class="md:col-span-2">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.audit.filter_action')) ?></span>
      <select name="action" class="w-full border rounded px-2 py-1.5">
        <option value=""><?= e($t('admin.audit.filter_action_any')) ?></option>
        <?php foreach ($actionPrefixes as $pfx): ?>
          <option value="<?= e($pfx . '.') ?>" <?= $filters['action'] === $pfx . '.' ? 'selected' : '' ?>><?= e($pfx . '.*') ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="md:col-span-2">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.audit.filter_user_id')) ?></span>
      <input name="user_id" value="<?= e($filters['user_id']) ?>" placeholder="1"
             class="w-full border rounded px-2 py-1.5">
    </label>

    <label class="md:col-span-2">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.audit.filter_target')) ?></span>
      <input name="target_type" value="<?= e($filters['target_type']) ?>" placeholder="user / page / issue"
             class="w-full border rounded px-2 py-1.5">
    </label>

    <label class="md:col-span-2">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.audit.filter_from')) ?></span>
      <input name="from" type="date" value="<?= e($filters['from']) ?>" class="w-full border rounded px-2 py-1.5">
    </label>

    <label class="md:col-span-2">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.audit.filter_to')) ?></span>
      <input name="to" type="date" value="<?= e($filters['to']) ?>" class="w-full border rounded px-2 py-1.5">
    </label>

    <label class="md:col-span-4">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.audit.filter_keyword')) ?></span>
      <input name="keyword" value="<?= e($filters['keyword']) ?>" placeholder="<?= e($t('admin.audit.filter_keyword_hint')) ?>"
             class="w-full border rounded px-2 py-1.5">
    </label>

    <label class="md:col-span-2">
      <span class="block text-xs text-slate-500 mb-1"><?= e($t('admin.audit.per_page')) ?></span>
      <select name="per_page" class="w-full border rounded px-2 py-1.5">
        <?php foreach ([20, 50, 100, 200, 500] as $n): ?>
          <option value="<?= $n ?>" <?= $perPage === $n ? 'selected' : '' ?>><?= $n ?></option>
        <?php endforeach; ?>
      </select>
    </label>

    <div class="md:col-span-6 flex items-end gap-2">
      <button class="bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700">
        <?= e($t('admin.audit.filter_apply')) ?>
      </button>
      <a href="<?= e(url('admin/audit')) ?>" class="text-slate-500 px-3 py-1.5 hover:underline">
        <?= e($t('admin.audit.filter_reset')) ?>
      </a>
    </div>
  </form>
</div>

<div class="bg-white rounded shadow">
  <div class="px-6 py-4 border-b flex items-center gap-3 flex-wrap">
    <h2 class="font-bold flex-1">
      <?= e($t('admin.audit.title')) ?>
      <span class="text-sm font-normal text-slate-500">(<?= (int)$result['total'] ?>)</span>
    </h2>

    <button type="button" id="copy-har"
            class="text-sm bg-slate-700 text-white px-3 py-1.5 rounded hover:bg-slate-800">
      <?= e($t('admin.audit.copy_har')) ?>
    </button>

    <button type="button" id="toggle-view"
            class="text-sm border rounded px-3 py-1.5 hover:bg-slate-50">
      <?= e($t('admin.audit.toggle_view')) ?>
    </button>

    <form method="post" action="<?= e(url('admin/audit/clear')) ?>"
          onsubmit="return confirm('<?= e($t('admin.audit.confirm_clear')) ?>')">
      <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
      <button class="text-sm text-red-600 hover:underline"><?= e($t('admin.audit.clear')) ?></button>
    </form>
  </div>

  <?php if (!$items): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('admin.audit.empty')) ?></div>
  <?php else: ?>
    <?php
    $lines = [];
    $blocks = [];
    foreach ($items as $entry) {
        $lines[]  = \Wiki\Models\AuditLog::toLine($entry);
        $blocks[] = \Wiki\Models\AuditLog::toFriendly($entry);
    }
    ?>
    <!-- Text (HAR-style) view, hidden by default -->
    <pre id="audit-text-view" class="hidden px-6 py-4 text-xs font-mono leading-relaxed whitespace-pre-wrap break-all bg-slate-900 text-slate-100 max-h-[70vh] overflow-auto"><?= e(implode("\n", $lines)) ?></pre>

    <!-- Friendly view -->
    <div id="audit-friendly-view" class="divide-y divide-slate-100">
      <?php foreach ($blocks as $b): ?>
        <div class="px-6 py-3 text-sm">
          <div class="flex items-start gap-3">
            <div class="font-mono text-xs text-slate-500 whitespace-nowrap"><?= e($b['time']) ?></div>
            <div class="flex-1 min-w-0 space-y-0.5">
              <div>
                <span class="text-slate-500"><?= e($t('admin.audit.label_actor')) ?>:</span>
                <span class="font-medium"><?= e($b['actor']) ?></span>
              </div>
              <div>
                <span class="text-slate-500"><?= e($t('admin.audit.label_event')) ?>:</span>
                <span class="font-mono text-blue-700"><?= e($b['event']) ?></span>
                <?php if ($b['target'] !== ''): ?>
                  <span class="text-slate-400 ml-2">→ <?= e($b['target']) ?></span>
                <?php endif; ?>
              </div>
              <?php if ($b['detail'] !== ''): ?>
                <div class="text-slate-600 break-all">
                  <span class="text-slate-500"><?= e($t('admin.audit.label_detail')) ?>:</span>
                  <span class="font-mono"><?= e($b['detail']) ?></span>
                </div>
              <?php endif; ?>
              <div class="text-xs text-slate-400 font-mono break-all">
                <span><?= e($t('admin.audit.label_ip')) ?>: <?= e($b['ip']) ?></span>
                <?php if ($b['ua'] !== ''): ?>
                  <span class="ml-3">UA: <?= e($b['ua']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <button type="button"
                    class="copy-one text-xs text-slate-400 hover:text-slate-700"
                    data-line="<?= e(\Wiki\Models\AuditLog::toLine($items[array_search($b, $blocks, true)] ?? [])) ?>"
                    title="<?= e($t('admin.audit.copy_one')) ?>">
              <?= e($t('admin.audit.copy_one_short')) ?>
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
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
          <?= e($t('admin.audit.page_of', ['page' => $page, 'pages' => $result['pages'], 'total' => $result['total']])) ?>
        </span>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<script>
(function () {
  const textView = document.getElementById('audit-text-view');
  const friendlyView = document.getElementById('audit-friendly-view');
  const toggleBtn = document.getElementById('toggle-view');
  const lines = <?= json_encode($lines ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

  toggleBtn?.addEventListener('click', function () {
    const showingText = !textView.classList.contains('hidden');
    if (showingText) {
      textView.classList.add('hidden');
      friendlyView.classList.remove('hidden');
    } else {
      textView.classList.remove('hidden');
      friendlyView.classList.add('hidden');
    }
  });

  document.getElementById('copy-har')?.addEventListener('click', function () {
    const payload = lines.join('\n');
    navigator.clipboard.writeText(payload).then(() => {
      const btn = this;
      const orig = btn.textContent;
      btn.textContent = '<?= e($t('admin.audit.copied')) ?>';
      setTimeout(() => { btn.textContent = orig; }, 1200);
    });
  });

  document.querySelectorAll('.copy-one').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const line = btn.getAttribute('data-line') || '';
      navigator.clipboard.writeText(line).then(() => {
        const orig = btn.textContent;
        btn.textContent = '<?= e($t('admin.audit.copied')) ?>';
        setTimeout(() => { btn.textContent = orig; }, 1200);
      });
    });
  });
})();
</script>
