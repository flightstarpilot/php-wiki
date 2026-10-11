<?php
$t = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$currentSlug = $group['slug'];
$baseContent = (string)$page['content'];
$baseTitle   = (string)$page['title'];

ob_start();
?>
<form method="post" action="<?= e(url('proposals')) ?>" class="bg-white rounded shadow">
  <input type="hidden" name="csrf" value="<?= e(\Wiki\Auth\Auth::csrf()) ?>">
  <input type="hidden" name="page_id" value="<?= (int)$page['id'] ?>">

  <header class="px-6 py-4 border-b">
    <h1 class="text-xl font-bold"><?= e($t('proposal.new_title')) ?></h1>
    <div class="mt-1 text-xs text-slate-400">
      <?= e($t('proposal.based_on', ['slug' => $group['slug'], 'v' => (int)$page['current_version']])) ?>
    </div>
  </header>

  <div class="px-6 py-4 space-y-3 border-b">
    <input name="title" required placeholder="<?= e($t('proposal.field_title')) ?>"
           class="w-full text-lg font-bold border rounded px-3 py-2">
    <textarea name="description" rows="2" placeholder="<?= e($t('proposal.field_description')) ?>"
              class="w-full border rounded px-3 py-2 text-sm"></textarea>
  </div>

  <div class="px-6 py-3 border-b bg-slate-50">
    <div class="text-xs text-slate-500 mb-1"><?= e($t('proposal.edit_body')) ?></div>
    <input name="proposed_title" value="<?= e($baseTitle) ?>" required
           class="w-full text-lg font-bold border rounded px-3 py-2 mb-2">
  </div>

  <div id="editor" class="border-b"></div>
  <textarea name="proposed_content" id="content_hidden" class="hidden"><?= e($baseContent) ?></textarea>

  <div class="px-6 py-4 flex gap-3">
    <button class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
      <?= e($t('proposal.submit')) ?>
    </button>
    <a href="<?= e(url('page/' . $group['slug'])) ?>"
       class="text-slate-500 px-4 py-2 rounded hover:bg-slate-100"><?= e($t('page.cancel')) ?></a>
  </div>
</form>

<script src="<?= e(url('vendor/toastui/toastui-editor-all.min.js')) ?>"></script>
<script>
const editor = new toastui.Editor({
  el: document.querySelector('#editor'),
  height: '520px',
  initialEditType: 'markdown',
  previewStyle: 'vertical',
  initialValue: document.getElementById('content_hidden').value,
  usageStatistics: false,
  toolbarItems: [
    ['heading','bold','italic','strike'],
    ['hr','quote'],
    ['ul','ol','task','indent','outdent'],
    ['table','image','link'],
    ['code','codeBlock'],
  ]
});
document.querySelector('form').addEventListener('submit', () => {
  document.getElementById('content_hidden').value = editor.getMarkdown();
});
</script>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';