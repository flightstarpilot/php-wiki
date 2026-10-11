<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$all = \Wiki\Models\Settings::all();
$defaults = \Wiki\Models\Settings::defaults();
$saveMsg = $saveMsg ?? null;
$testResult = $_SESSION['flash_mail_test'] ?? null;
unset($_SESSION['flash_mail_test']);

$fields = [
    'mail.tpl.verify_subject' => ['type' => 'text'],
    'mail.tpl.verify_body'    => ['type' => 'textarea'],
    'mail.tpl.reset_subject'  => ['type' => 'text'],
    'mail.tpl.reset_body'     => ['type' => 'textarea'],
];

$label = function (string $key) use ($t, $defaults): string {
    $lk = 'settings.label.' . $key;
    $v = $t($lk);
    return $v === $lk ? ($defaults[$key] ?? $key) : $v;
};

$hint = function (string $key) use ($t): string {
    $hk = 'settings.hint.' . $key;
    $v = $t($hk);
    return $v === $hk ? '' : $v;
};
?>

<?php if ($saveMsg): ?>
  <div class="border rounded p-3 mb-4 <?= $saveMsg['type'] === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <?= e($saveMsg['text']) ?>
  </div>
<?php endif; ?>

<?php if ($testResult): ?>
  <div class="border rounded p-3 mb-4 <?= $testResult['ok'] ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
    <?= e($testResult['message']) ?>
  </div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/mail_templates')) ?>" class="space-y-6">
  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">

  <div class="bg-white rounded shadow p-6">
    <p class="text-xs text-slate-500 mb-6"><?= e($t('admin.mail_templates.hint')) ?></p>

    <div class="space-y-5">
      <?php foreach ($fields as $k => $meta):
          $val = $all[$k] ?? $defaults[$k] ?? '';
          $labelText = $label($k);
          $hintText  = $hint($k);
      ?>
        <div>
          <label class="block">
            <span class="text-sm font-medium text-slate-700"><?= e($labelText) ?></span>
            <?php if ($hintText !== ''): ?>
              <span class="text-xs text-slate-400 block mt-0.5"><?= e($hintText) ?></span>
            <?php endif; ?>

            <?php if ($meta['type'] === 'textarea'): ?>
              <textarea name="settings[<?= e($k) ?>]" rows="6"
                        class="mt-1 w-full border rounded px-3 py-2 font-mono text-sm"><?= e((string)$val) ?></textarea>
            <?php else: ?>
              <input name="settings[<?= e($k) ?>]" value="<?= e((string)$val) ?>"
                     class="mt-1 w-full border rounded px-3 py-2">
            <?php endif; ?>
          </label>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="bg-white rounded shadow p-6">
    <h2 class="font-bold mb-4"><?= e($t('admin.mail_templates.test')) ?></h2>
    <div class="flex items-center gap-3">
      <input name="test_to" type="email" placeholder="<?= e($t('admin.mail_templates.test_to')) ?>"
             class="border rounded px-3 py-2 flex-1">
      <button type="submit" formaction="<?= e(url('admin/mail_templates/test')) ?>"
              class="bg-slate-700 text-white px-4 py-2 rounded hover:bg-slate-800">
        <?= e($t('admin.mail_templates.send_test')) ?>
      </button>
    </div>
  </div>

  <div class="sticky bottom-4">
    <button class="bg-blue-600 text-white px-6 py-2 rounded shadow hover:bg-blue-700">
      <?= e($t('admin.save_all')) ?>
    </button>
  </div>
</form>
