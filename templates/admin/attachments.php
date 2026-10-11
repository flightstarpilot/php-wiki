<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$items = \Wiki\Services\AttachmentService::listAll(200, 0);
$driver = \Wiki\Services\AttachmentService::driver();
?>

<div class="bg-white rounded shadow">
  <div class="px-6 py-4 border-b flex items-center gap-3">
    <h2 class="font-bold flex-1"><?= e($t('admin.attachments.title')) ?></h2>
    <span class="text-xs text-slate-500"><?= e($t('admin.attachments.driver', ['driver' => $driver])) ?></span>
  </div>

  <?php if (!$items): ?>
    <div class="px-6 py-8 text-sm text-slate-500"><?= e($t('admin.attachments.empty')) ?></div>
  <?php else: ?>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-6">
      <?php foreach ($items as $a): ?>
        <div class="border rounded overflow-hidden bg-slate-50">
          <div class="h-32 bg-white flex items-center justify-center overflow-hidden">
            <?php if (str_starts_with((string)$a['mime_type'], 'image/')): ?>
              <img src="<?= e($a['url']) ?>" alt="" class="max-h-32 max-w-full object-contain">
            <?php else: ?>
              <span class="text-slate-400"><?= icon('file', 'w-10 h-10') ?></span>
            <?php endif; ?>
          </div>
          <div class="p-2 text-xs">
            <div class="truncate font-medium" title="<?= e($a['original_name']) ?>"><?= e($a['original_name']) ?></div>
            <div class="text-slate-400 mt-0.5 flex items-center justify-between">
              <span><?= e(number_format((int)$a['size'] / 1024, 1)) ?> KB</span>
              <span><?= e($a['storage']) ?></span>
            </div>
            <div class="text-slate-400 mt-0.5 truncate"><?= e($a['uploader'] ?? '') ?></div>
            <div class="mt-2 flex items-center gap-2">
              <button type="button" class="text-blue-600 hover:underline"
                      onclick="navigator.clipboard.writeText('<?= e($a['url']) ?>')">
                <?= e($t('admin.attachments.copy_url')) ?>
              </button>
              <form method="post" action="<?= e(url('admin/attachments/delete/' . (int)$a['id'])) ?>"
                    onsubmit="return confirm('<?= e($t('admin.attachments.confirm_delete')) ?>')" class="ml-auto">
                <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
                <button class="text-red-600 hover:underline"><?= e($t('admin.attachments.delete')) ?></button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
