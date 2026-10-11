<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$cfg = require WIKI_ROOT . '/config/config.php';
$driver = (string)($cfg['db']['driver'] ?? 'sqlite');
$flash = $_SESSION['flash_storage'] ?? null;
unset($_SESSION['flash_storage']);
$cacheAvailable = \Wiki\Services\CacheService::available();
?>

<?php if ($flash): ?>
  <div class="border rounded p-3 mb-4 <?= $flash['ok'] ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <?= e($flash['message']) ?>
  </div>
<?php endif; ?>

<div class="bg-white rounded shadow p-6 space-y-4 mb-6">
  <div>
    <div class="text-xs uppercase text-slate-400 mb-1"><?= e($t('admin.storage.driver')) ?></div>
    <div class="font-mono"><?= e($driver) ?></div>
  </div>

  <?php if ($driver === 'http'): ?>
    <div>
      <div class="text-xs uppercase text-slate-400 mb-1"><?= e($t('admin.storage.endpoint')) ?></div>
      <div class="font-mono text-sm break-all"><?= e((string)($cfg['db']['endpoint'] ?? '')) ?></div>
    </div>
    <div>
      <div class="text-xs uppercase text-slate-400 mb-1"><?= e($t('admin.storage.auth_type')) ?></div>
      <div class="font-mono text-sm"><?= e((string)($cfg['db']['auth_type'] ?? 'none')) ?></div>
    </div>
  <?php elseif ($driver === 'sqlite'): ?>
    <div>
      <div class="text-xs uppercase text-slate-400 mb-1"><?= e($t('admin.storage.path')) ?></div>
      <div class="font-mono text-sm break-all"><?= e((string)($cfg['db']['path'] ?? '')) ?></div>
    </div>
  <?php else: ?>
    <div>
      <div class="text-xs uppercase text-slate-400 mb-1"><?= e($t('admin.storage.host')) ?></div>
      <div class="font-mono text-sm">
        <?= e((string)($cfg['db']['username'] ?? '')) ?>@<?= e((string)($cfg['db']['host'] ?? '')) ?>:<?= (int)($cfg['db']['port'] ?? 0) ?>/<?= e((string)($cfg['db']['database'] ?? '')) ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="pt-4 border-t flex items-center gap-3 flex-wrap">
    <form method="post" action="<?= e(url('admin/storage/test')) ?>">
      <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
      <button class="bg-slate-700 text-white px-3 py-1.5 rounded text-sm">
        <?= e($t('admin.storage.test')) ?>
      </button>
    </form>
    <?php if ($driver === 'sqlite'): ?>
      <form method="post" action="<?= e(url('admin/storage/vacuum')) ?>"
            onsubmit="return confirm('<?= e($t('admin.storage.confirm_vacuum')) ?>')">
        <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
        <button class="text-sm px-3 py-1.5 border rounded text-slate-600 hover:bg-slate-50">
          <?= e($t('admin.storage.vacuum')) ?>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="bg-white rounded shadow p-6 space-y-4">
  <div>
    <div class="text-xs uppercase text-slate-400 mb-1"><?= e($t('admin.storage.cache')) ?></div>
    <div class="font-mono text-sm">
      <?= e((string)\Wiki\Models\Settings::get('storage.cache', 'none')) ?>
      <?= $cacheAvailable ? ' (' . e($t('admin.storage.cache_connected')) . ')' : ' (' . e($t('admin.storage.cache_disconnected')) . ')' ?>
    </div>
  </div>
  <div class="pt-4 border-t flex items-center gap-3">
    <form method="post" action="<?= e(url('admin/storage/cache_test')) ?>">
      <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
      <button class="text-sm px-3 py-1.5 border rounded text-slate-600 hover:bg-slate-50">
        <?= e($t('admin.storage.cache_test')) ?>
      </button>
    </form>
    <form method="post" action="<?= e(url('admin/storage/cache_flush')) ?>">
      <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
      <button class="text-sm px-3 py-1.5 border rounded text-slate-600 hover:bg-slate-50">
        <?= e($t('admin.storage.cache_flush')) ?>
      </button>
    </form>
  </div>
</div>
