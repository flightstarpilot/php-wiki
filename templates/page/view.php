<?php
$pageTitle   = $page['title'] ?? $group['slug'];
$currentSlug = $group['slug'];
$defaultLang = (string)\Wiki\Models\Settings::get('site.default_lang');
$t           = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);

$viewLang = $page['lang'] ?? $lang;
$viewUrl  = url('page/' . $group['slug'] . ($viewLang === $defaultLang ? '' : '/' . $viewLang));

$featureIssues    = \Wiki\Models\Settings::getBool('feature.issues');
$featureProposals = \Wiki\Models\Settings::getBool('feature.proposals');
$openIssues       = 0;
$openProposals    = 0;
$pageProposals    = [];
if ($page) {
    if ($featureIssues) {
        $rows = \Wiki\Models\Issue::listByGroup((int)$group['id']);
        foreach ($rows as $r) if ($r['status'] === 'open') $openIssues++;
    }
    if ($featureProposals) {
        $pageProposals = \Wiki\Models\Proposal::listByPage((int)$page['id']);
        foreach ($pageProposals as $r) if ($r['status'] === 'open') $openProposals++;
    }
}
$pageTags = $pageTags ?? [];

ob_start();
?>
<article class="bg-white rounded shadow">
  <header class="px-6 py-4 border-b flex items-center gap-3 flex-wrap">
    <h1 class="text-2xl font-bold"><?= e($page['title'] ?? $group['slug']) ?></h1>

    <div class="ml-auto flex items-center gap-1 text-sm">
      <?php foreach ($allLangs as $l):
        $isCur = $page && $l['lang'] === $page['lang'];
        $href  = url('page/' . $group['slug'] . ($l['lang'] === $defaultLang ? '' : '/' . $l['lang']));
      ?>
        <a href="<?= e($href) ?>"
           class="px-2 py-1 rounded <?= $isCur ? 'bg-blue-100 text-blue-700' : 'text-slate-500 hover:bg-slate-100' ?>">
          <?= e($l['lang']) ?>
        </a>
      <?php endforeach; ?>

      <?php if ($page && \Wiki\Models\Settings::getBool('feature.history')): ?>
        <a href="<?= e(url('page/' . $group['slug'] . '/' . $viewLang . '/history')) ?>"
           class="flex items-center gap-1 px-2 py-1 rounded text-slate-500 hover:bg-slate-100">
          <?= icon('history', 'w-4 h-4') ?><span><?= e($t('page.history')) ?></span>
        </a>
      <?php endif; ?>

      <?php if (\Wiki\Auth\Auth::isEditor()): ?>
        <a href="<?= e(url('page/' . $group['slug'] . '/' . $viewLang . '/edit')) ?>"
           class="ml-2 flex items-center gap-1 px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700">
          <?= icon('edit', 'w-4 h-4') ?><span><?= e($t('page.edit')) ?></span>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($fallback && $page): ?>
    <div class="bg-amber-50 text-amber-700 text-sm px-6 py-2 border-b flex items-center gap-2">
      <?= icon('alert', 'w-4 h-4') ?>
      <span><?= e($t('page.fallback_notice', ['lang' => $lang, 'fallback' => $page['lang']])) ?></span>
      <?php if (\Wiki\Auth\Auth::isEditor()): ?>
        <a class="underline ml-2" href="<?= e(url('page/' . $group['slug'] . '/' . $lang . '/edit')) ?>"><?= e($t('page.translate')) ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($page && (int)$page['is_locked'] === 1): ?>
    <div class="bg-amber-50 text-amber-700 text-sm px-6 py-2 border-b flex items-center gap-2">
      <?= icon('lock', 'w-4 h-4') ?>
      <span><?= e($t('page.locked')) ?><?= !empty($page['locked_reason']) ? ': ' . e($page['locked_reason']) : '' ?></span>
    </div>
  <?php endif; ?>

  <div class="markdown-body px-6 py-6">
    <?php if ($page): ?>
      <?= $html ?>
    <?php else: ?>
      <p class="text-slate-500"><?= e($t('page.no_content')) ?></p>
      <?php if (\Wiki\Auth\Auth::isEditor()): ?>
        <a href="<?= e(url('page/' . $group['slug'] . '/' . $lang . '/edit')) ?>"
           class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded">
          <?= e($t('page.create_this_lang')) ?>
        </a>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <?php if ($pageTags): ?>
    <div class="px-6 py-3 border-t flex items-center gap-2 flex-wrap text-sm">
      <span class="text-slate-400 flex items-center gap-1"><?= icon('file', 'w-4 h-4') ?><?= e($t('tag.title')) ?></span>
      <?php foreach ($pageTags as $tag): ?>
        <a href="<?= e(url('tag/' . $tag['slug'])) ?>"
           class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 hover:bg-blue-100 hover:text-blue-700 text-xs">
          <?= e($tag['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($page && ($featureIssues || $featureProposals)): ?>
    <footer class="px-6 py-3 border-t bg-slate-50 flex items-center gap-4 text-sm flex-wrap">
      <?php if ($featureIssues): ?>
        <a href="<?= e(url('issues?status=open')) ?>" class="flex items-center gap-1 text-slate-600 hover:text-slate-900">
          <?= icon('alert', 'w-4 h-4') ?>
          <span><?= e($t('issue.open_count', ['n' => $openIssues])) ?></span>
        </a>
      <?php endif; ?>
      <?php if ($featureProposals): ?>
        <a href="<?= e(url('proposals?status=open')) ?>" class="flex items-center gap-1 text-slate-600 hover:text-slate-900">
          <?= icon('compare', 'w-4 h-4') ?>
          <span><?= e($t('proposal.open_count', ['n' => $openProposals])) ?></span>
        </a>
        <?php if (\Wiki\Auth\Auth::check()): ?>
          <a href="<?= e(url('page/' . $group['slug'] . '/' . $viewLang . '/propose')) ?>"
             class="flex items-center gap-1 text-blue-600 hover:underline">
            <?= icon('edit', 'w-4 h-4') ?>
            <span><?= e($t('proposal.suggest')) ?></span>
          </a>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($featureIssues && \Wiki\Auth\Auth::check()): ?>
        <a href="<?= e(url('issues/new?group=' . (int)$group['id'])) ?>"
           class="flex items-center gap-1 text-blue-600 hover:underline">
          <?= icon('alert', 'w-4 h-4') ?>
          <span><?= e($t('issue.open_new')) ?></span>
        </a>
      <?php endif; ?>
    </footer>
  <?php endif; ?>
</article>

<?php if (!empty($backlinks)): ?>
<aside class="bg-white rounded shadow mt-4 p-4">
  <div class="text-xs uppercase text-slate-400 mb-2"><?= e($t('page.backlinks')) ?></div>
  <ul class="text-sm space-y-1">
    <?php foreach ($backlinks as $b): ?>
      <li><a class="text-blue-600 hover:underline" href="<?= e(url('page/' . $b['slug'])) ?>"><?= e($b['title']) ?></a></li>
    <?php endforeach; ?>
  </ul>
</aside>
<?php endif; ?>

<?php
$content = ob_get_clean();
require WIKI_ROOT . '/templates/layout.php';