<?php
$siteName    = \Wiki\Models\Settings::get('site.name');
$user        = \Wiki\Auth\Auth::user();
$tree        = \Wiki\Services\TreeService::build();
$currentSlug = $currentSlug ?? '';
$pageTitle   = $pageTitle ?? $siteName;
$t           = fn(string $k, array $p = []) => \Wiki\Services\I18nService::t($k, $p);
$curLang     = \Wiki\Services\I18nService::current();
$uiLangs     = \Wiki\Services\I18nService::supported();

$featureIssues    = \Wiki\Models\Settings::getBool('feature.issues');
$featureProposals = \Wiki\Models\Settings::getBool('feature.proposals');
$featureSearch    = \Wiki\Models\Settings::getBool('feature.search');
$featureTags      = \Wiki\Models\Settings::getBool('feature.tags');
$openIssues       = $featureIssues    ? \Wiki\Models\Issue::countOpen()    : 0;
$openProposals    = $featureProposals ? \Wiki\Models\Proposal::countOpen() : 0;

$favicon    = (string)\Wiki\Models\Settings::get('site.favicon', '');
$logo       = (string)\Wiki\Models\Settings::get('site.logo', '');
$customCss  = (string)\Wiki\Models\Settings::get('ui.custom_css', '');
$customJs   = (string)\Wiki\Models\Settings::get('ui.custom_js', '');
$footerHtml = (string)\Wiki\Models\Settings::get('site.footer', '');
?>
<!doctype html>
<html lang="<?= e($curLang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e($siteName) ?></title>
<?php if (\Wiki\Models\Settings::get('seo.robots') !== 'index,follow'): ?>
  <meta name="robots" content="<?= e((string)\Wiki\Models\Settings::get('seo.robots')) ?>">
<?php endif; ?>
<?php if ($favicon !== ''): ?>
  <link rel="icon" href="<?= e($favicon) ?>">
<?php endif; ?>
<script src="<?= e(url('assets/tailwind.min.js')) ?>"></script>
<link rel="stylesheet" href="<?= e(url('vendor/toastui/toastui-editor.min.css')) ?>">
<style>
  .markdown-body h1{font-size:1.6rem;font-weight:700;margin:1.4rem 0 .8rem;}
  .markdown-body h2{font-size:1.3rem;font-weight:700;margin:1.2rem 0 .6rem;}
  .markdown-body h3{font-size:1.1rem;font-weight:700;margin:1rem 0 .5rem;}
  .markdown-body p{margin:.6rem 0;line-height:1.75;}
  .markdown-body ul{list-style:disc;padding-left:1.4rem;margin:.6rem 0;}
  .markdown-body ol{list-style:decimal;padding-left:1.4rem;margin:.6rem 0;}
  .markdown-body code{background:#f1f5f9;padding:1px 5px;border-radius:4px;font-size:.9em;}
  .markdown-body pre{background:#0f172a;color:#e2e8f0;padding:14px;border-radius:8px;overflow:auto;margin:.8rem 0;}
  .markdown-body pre code{background:none;color:inherit;padding:0;}
  .markdown-body blockquote{border-left:3px solid #cbd5e1;padding-left:1rem;color:#64748b;margin:.8rem 0;}
  .markdown-body a{color:#2563eb;text-decoration:underline;}
  .markdown-body table{border-collapse:collapse;margin:.8rem 0;}
  .markdown-body th,.markdown-body td{border:1px solid #e2e8f0;padding:6px 10px;}
  .markdown-body img{max-width:100%;border-radius:6px;}
  .markdown-body mark{background:#fef08a;padding:0 2px;border-radius:2px;}
</style>
<?php if ($customCss !== ''): ?>
<style><?= $customCss ?></style>
<?php endif; ?>
</head>
<body class="bg-slate-50 min-h-screen">
<header class="bg-white border-b sticky top-0 z-20">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-4">
    <a href="<?= e(url('')) ?>" class="flex items-center gap-2 text-lg font-bold shrink-0">
      <?php if ($logo !== ''): ?>
        <?php if (str_starts_with($logo, '<svg') || str_starts_with($logo, '<img')): ?>
          <?= $logo ?>
        <?php else: ?>
          <img src="<?= e($logo) ?>" alt="" class="w-6 h-6">
        <?php endif; ?>
      <?php else: ?>
        <?= icon('home', 'w-5 h-5 text-slate-500') ?>
      <?php endif; ?>
      <span><?= e($siteName) ?></span>
    </a>

    <nav class="hidden md:flex items-center gap-1 text-sm">
      <?php if ($featureIssues): ?>
        <a href="<?= e(url('issues')) ?>" class="flex items-center gap-1 px-2 py-1 rounded text-slate-600 hover:bg-slate-100">
          <?= icon('alert', 'w-4 h-4') ?><span><?= e($t('nav.issues')) ?></span>
          <?php if ($openIssues > 0): ?><span class="text-xs bg-slate-200 text-slate-600 px-1.5 rounded"><?= (int)$openIssues ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <?php if ($featureProposals): ?>
        <a href="<?= e(url('proposals')) ?>" class="flex items-center gap-1 px-2 py-1 rounded text-slate-600 hover:bg-slate-100">
          <?= icon('compare', 'w-4 h-4') ?><span><?= e($t('nav.proposals')) ?></span>
          <?php if ($openProposals > 0): ?><span class="text-xs bg-slate-200 text-slate-600 px-1.5 rounded"><?= (int)$openProposals ?></span><?php endif; ?>
        </a>
      <?php endif; ?>
      <?php if ($featureTags): ?>
        <a href="<?= e(url('tags')) ?>" class="flex items-center gap-1 px-2 py-1 rounded text-slate-600 hover:bg-slate-100">
          <?= icon('file', 'w-4 h-4') ?><span><?= e($t('nav.tags')) ?></span>
        </a>
      <?php endif; ?>
    </nav>

    <div class="ml-auto flex items-center gap-3 text-sm">
      <?php if ($featureSearch): ?>
        <form method="get" action="<?= e(url('search')) ?>" class="hidden sm:flex items-center">
          <input name="q" placeholder="<?= e($t('search.placeholder')) ?>"
                 class="border rounded-l px-2 py-1 text-sm w-40 focus:w-56 transition-all">
          <button class="border border-l-0 rounded-r px-2 py-1 text-slate-500 hover:bg-slate-100">
            <?= icon('search', 'w-4 h-4') ?>
          </button>
        </form>
      <?php endif; ?>

      <?php if (\Wiki\Auth\Auth::isEditor()): ?>
        <a href="<?= e(url('new')) ?>" class="flex items-center gap-1 text-blue-600 hover:underline">
          <?= icon('plus', 'w-4 h-4') ?><span class="hidden sm:inline"><?= e($t('nav.new')) ?></span>
        </a>
      <?php endif; ?>

      <?php /* Language switcher: the pt-1 bridge keeps hover alive across the gap */ ?>
      <div class="relative group">
        <button type="button" class="flex items-center gap-1 text-slate-500 hover:text-slate-900">
          <?= icon('globe', 'w-4 h-4') ?><span><?= e($curLang) ?></span>
        </button>
        <div class="hidden group-hover:block absolute right-0 top-full pt-1 min-w-[140px] z-30">
          <div class="bg-white border rounded shadow-lg py-1">
            <?php foreach ($uiLangs as $lc): ?>
              <a href="<?= e(url('lang/' . $lc)) ?>"
                 class="block px-3 py-1 text-sm hover:bg-slate-100 <?= $lc === $curLang ? 'text-blue-600 font-medium' : 'text-slate-700' ?>">
                <?= e($lc) ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <?php if ($user): ?>
        <div class="relative group">
          <button type="button" class="flex items-center gap-1 text-slate-600 hover:text-slate-900">
            <?= icon('user', 'w-4 h-4') ?><span><?= e($user['display_name'] ?: $user['username']) ?></span>
          </button>
          <div class="hidden group-hover:block absolute right-0 top-full pt-1 min-w-[160px] z-30">
            <div class="bg-white border rounded shadow-lg py-1">
              <a href="<?= e(url('profile')) ?>" class="block px-3 py-1 text-sm hover:bg-slate-100">
                <?= e($t('nav.profile')) ?>
              </a>
              <?php if (\Wiki\Auth\Auth::isAdmin()): ?>
                <a href="<?= e(url('admin')) ?>" class="block px-3 py-1 text-sm hover:bg-slate-100">
                  <?= e($t('nav.admin')) ?>
                </a>
              <?php endif; ?>
              <a href="<?= e(url('logout')) ?>" class="block px-3 py-1 text-sm hover:bg-slate-100 text-red-600">
                <?= e($t('nav.logout')) ?>
              </a>
            </div>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= e(url('login')) ?>" class="text-slate-500 hover:text-slate-900"><?= e($t('nav.login')) ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<div class="max-w-7xl mx-auto px-4 py-6 flex gap-6">
  <aside class="w-64 shrink-0 hidden md:block">
    <div class="bg-white rounded shadow p-3 sticky top-20 max-h-[calc(100vh-6rem)] overflow-auto">
      <div class="text-xs uppercase text-slate-400 mb-2 px-2"><?= e($t('nav.tree')) ?></div>
      <?= \Wiki\Services\TreeService::render($tree, $currentSlug) ?>
    </div>
  </aside>
  <main class="flex-1 min-w-0">
    <?= $content ?>
  </main>
</div>

<?php if ($footerHtml !== ''): ?>
<footer class="mt-8 py-4 text-center text-xs text-slate-400">
  <?= $footerHtml ?>
</footer>
<?php endif; ?>

<?php if ($customJs !== ''): ?>
<script><?= $customJs ?></script>
<?php endif; ?>
</body>
</html>
