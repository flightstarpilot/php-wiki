<?php
$isNew       = !$page;
$defaultLang = (string)\Wiki\Models\Settings::get('site.default_lang');
$prefillSlug = $prefillSlug ?? '';
$groupSlug   = $group['slug'] ?? ($isNew ? $prefillSlug : '');
$lang        = $lang ?? $defaultLang;
$titleVal    = $page['title']   ?? '';
$contentVal  = $page['content'] ?? '';
$tagString   = $tagString ?? '';
$t           = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);

$pageTitle   = $isNew
    ? $t('page.new_title')
    : $t('page.edit_title', ['title' => $page['title'] ?? '']);
$currentSlug = $groupSlug;

$attachmentsEnabled = \Wiki\Models\Settings::getBool('feature.attachments');
$maxSize = \Wiki\Services\AttachmentService::maxSize();
$uploadUrl = url('api/upload');
$csrfToken = \Wiki\Auth\Auth::csrf();

ob_start();
?>
<form id="page-edit-form" method="post" action="<?= e(url('page/save')) ?>" class="bg-white rounded shadow">
  <input type="hidden" name="csrf" value="<?= e($csrfToken) ?>">
  <input type="hidden" name="original_slug" value="<?= e($groupSlug) ?>">
  <input type="hidden" name="lang" value="<?= e($lang) ?>">
  <?php if ($isNew): ?>
    <input type="hidden" name="new_group" value="1">
  <?php endif; ?>
  <?php if ($isNew && $prefillSlug !== ''): ?>
    <input type="hidden" name="slug" value="<?= e($prefillSlug) ?>">
  <?php endif; ?>

  <div class="px-6 py-4 border-b space-y-3">
    <div class="flex items-center gap-3 flex-wrap">
      <input name="title" value="<?= e($titleVal) ?>" placeholder="<?= e($t('page.title_placeholder')) ?>"
             class="flex-1 min-w-[200px] text-xl font-bold border rounded px-3 py-2" required autofocus>
      <span class="text-sm text-slate-500 border rounded px-3 py-2 bg-slate-50"><?= e($lang) ?></span>
    </div>

    <label class="block">
      <span class="text-xs text-slate-500"><?= e($t('page.slug')) ?></span>
      <input name="slug" value="<?= e($groupSlug) ?>"
             placeholder="<?= e($t('page.slug_placeholder')) ?>"
             class="mt-1 w-full border rounded px-3 py-2 text-sm font-mono">
      <span class="text-xs text-slate-400">
        <?= e($isNew ? $t('page.slug_hint_new') : $t('page.slug_hint')) ?>
      </span>
    </label>

    <?php if (\Wiki\Models\Settings::getBool('feature.tags')): ?>
      <input name="tags" value="<?= e($tagString) ?>" placeholder="<?= e($t('page.tags_placeholder')) ?>"
             class="w-full border rounded px-3 py-2 text-sm">
    <?php endif; ?>
    <input name="comment" placeholder="<?= e($t('page.comment_placeholder')) ?>"
           class="w-full border rounded px-3 py-2 text-sm">
  </div>

  <div id="editor" class="border-b"></div>
  <textarea name="content" id="content_hidden" class="hidden"><?= e($contentVal) ?></textarea>

  <?php if ($attachmentsEnabled): ?>
    <div id="dropzone" class="px-6 py-3 border-b bg-slate-50 text-xs text-slate-500 text-center cursor-pointer">
      <?= e($t('attachment.drop_hint', ['size' => number_format($maxSize / 1024 / 1024, 1)])) ?>
    </div>
  <?php endif; ?>

  <div class="px-6 py-4 flex gap-3">
    <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700"><?= e($t('page.save')) ?></button>
    <a href="<?= e($groupSlug ? url('page/' . $groupSlug) : url('')) ?>"
       class="text-slate-500 px-4 py-2 rounded hover:bg-slate-100"><?= e($t('page.cancel')) ?></a>
  </div>
</form>

<script src="<?= e(url('vendor/toastui/toastui-editor-all.min.js')) ?>"></script>
<script>
(function() {
  const csrfToken = <?= json_encode($csrfToken) ?>;
  const uploadUrl = <?= json_encode($uploadUrl) ?>;
  const attachmentsEnabled = <?= $attachmentsEnabled ? 'true' : 'false' ?>;
  const maxSize = <?= (int)$maxSize ?>;
  const messages = {
    uploadFailed: <?= json_encode($t('attachment.upload_failed')) ?>,
    tooLarge:     <?= json_encode($t('attachment.too_large')) ?>
  };

  async function uploadFile(file) {
    if (!attachmentsEnabled) return null;
    if (file.size > maxSize) {
      alert(messages.tooLarge.replace('{size}', (maxSize / 1024 / 1024).toFixed(1)));
      return null;
    }
    const fd = new FormData();
    fd.append('file', file);
    fd.append('csrf', csrfToken);

    const res = await fetch(uploadUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json.url) {
      alert(messages.uploadFailed + (json.error ? ': ' + json.error : ''));
      return null;
    }
    return json;
  }

  const editor = new toastui.Editor({
    el: document.querySelector('#editor'),
    height: '560px',
    initialEditType: 'markdown',
    initialValue: document.getElementById('content_hidden').value,
    usageStatistics: false
  });

  function syncContent() {
    document.getElementById('content_hidden').value = editor.getMarkdown();
  }
  document.getElementById('page-edit-form').addEventListener('submit', syncContent);
  if (editor.on) editor.on('change', syncContent);
  document.getElementById('page-edit-form').addEventListener('input', syncContent);
  syncContent();

  if (attachmentsEnabled) {
    const dz = document.getElementById('dropzone');

    ['dragenter', 'dragover'].forEach(ev =>
      document.addEventListener(ev, e => { e.preventDefault(); dz.classList.add('bg-blue-50'); })
    );
    ['dragleave', 'drop'].forEach(ev =>
      document.addEventListener(ev, e => { e.preventDefault(); dz.classList.remove('bg-blue-50'); })
    );

    document.addEventListener('drop', async e => {
      if (!e.dataTransfer || !e.dataTransfer.files.length) return;
      for (const file of e.dataTransfer.files) {
        const r = await uploadFile(file);
        if (!r) continue;
        const md = file.type.startsWith('image/')
          ? '![' + r.filename + '](' + r.url + ')'
          : '[' + r.filename + '](' + r.url + ')';
        const current = editor.getMarkdown();
        editor.setMarkdown(current + '\n' + md + '\n');
      }
    });

    dz.addEventListener('click', () => {
      const input = document.createElement('input');
      input.type = 'file';
      input.multiple = true;
      input.onchange = async () => {
        for (const file of input.files) {
          const r = await uploadFile(file);
          if (!r) continue;
          const md = file.type.startsWith('image/')
            ? '![' + r.filename + '](' + r.url + ')'
            : '[' + r.filename + '](' + r.url + ')';
          const current = editor.getMarkdown();
          editor.setMarkdown(current + '\n' + md + '\n');
        }
      };
      input.click();
    });
  }
})();
</script>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';
