<?php
declare(strict_types=1);

namespace Wiki\Controllers;

use Wiki\Auth\Auth;
use Wiki\Models\AuditLog;
use Wiki\Models\NotFoundLog;
use Wiki\Models\Page;
use Wiki\Models\Redirect;
use Wiki\Models\Settings;
use Wiki\Models\Tag;
use Wiki\Models\TranslationGroup;
use Wiki\Services\DiffService;
use Wiki\Services\I18nService;
use Wiki\Services\MarkdownService;

class PageController
{
    /**
     * Front page. Falls back to a first-run welcome screen when the
     * configured home page does not exist yet.
     */
    public function home(): void
    {
        $home = (string)Settings::get('site.home_slug', 'home');
        $group = TranslationGroup::findBySlug($home);

        if ($group) {
            $this->view(['slug' => $home]);
            return;
        }

        // No home page yet: render the welcome / onboarding screen.
        $siteName = (string)Settings::get('site.name', 'Wiki');
        $pageTitle = I18nService::t('welcome.title');
        $currentSlug = '';
        $canCreate = Auth::isEditor();
        $isLoggedIn = Auth::check();

        ob_start();
        ?>
        <div class="bg-white rounded shadow p-10 text-center max-w-2xl mx-auto">
          <div class="text-5xl font-bold text-slate-800 mb-3"><?= e($siteName) ?></div>
          <p class="text-slate-500 mb-8"><?= e(I18nService::t('welcome.subtitle')) ?></p>

          <?php if (!$isLoggedIn): ?>
            <p class="text-sm text-slate-500 mb-4"><?= e(I18nService::t('welcome.login_hint')) ?></p>
            <div class="flex items-center justify-center gap-3">
              <a href="<?= e(url('login')) ?>"
                 class="bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700">
                <?= e(I18nService::t('nav.login')) ?>
              </a>
            </div>
          <?php elseif (!$canCreate): ?>
            <p class="text-sm text-slate-500"><?= e(I18nService::t('welcome.no_permission')) ?></p>
          <?php else: ?>
            <p class="text-sm text-slate-500 mb-4"><?= e(I18nService::t('welcome.admin_hint')) ?></p>
            <a href="<?= e(url('new')) ?>"
               class="inline-block bg-blue-600 text-white px-5 py-2 rounded hover:bg-blue-700">
              <?= e(I18nService::t('welcome.create_home')) ?>
            </a>
            <div class="text-xs text-slate-400 mt-6 leading-relaxed">
              <?= e(I18nService::t('welcome.home_slug_hint', ['slug' => $home])) ?>
            </div>
          <?php endif; ?>
        </div>
        <?php
        $content = ob_get_clean();
        require WIKI_ROOT . '/templates/layout.php';
    }

    public function view(array $params): void
    {
        $slug = $params['slug'];
        $lang = $params['lang'] ?? null;

        $group = TranslationGroup::findBySlug($slug);
        if (!$group) { $this->notFound('/page/' . $slug); return; }

        $defaultLang = (string)Settings::get('site.default_lang', $group['default_lang']);
        $requestedLang = $lang ?? $defaultLang;

        $page = Page::findByGroupLang((int)$group['id'], $requestedLang);

        $fallback = false;
        if (!$page) {
            $page = Page::findByGroupLang((int)$group['id'], $group['default_lang']);
            $fallback = $page !== null;
        }

        $html      = $page ? MarkdownService::render((string)($page['content'] ?? '')) : '';
        $allLangs  = Page::listByGroup((int)$group['id']);
        $backlinks = $page ? Page::backlinks((int)$page['id']) : [];
        $pageTags  = $page ? Tag::listForPage((int)$page['id']) : [];

        $viewLang = $page['lang'] ?? $requestedLang;

        require WIKI_ROOT . '/templates/page/view.php';
    }

    public function edit(array $params): void
    {
        if (!Auth::isEditor()) { $this->forbidden(); return; }

        $slug = $params['slug'];
        $lang = $params['lang'] ?? (string)Settings::get('site.default_lang');

        $group = TranslationGroup::findBySlug($slug);
        if (!$group) { $this->notFound('/page/' . $slug); return; }

        $page = Page::findByGroupLang((int)$group['id'], $lang);

        if ($page && (int)$page['is_locked'] === 1 && !Auth::isAdmin()) {
            $this->forbidden(I18nService::t('page.locked'));
            return;
        }

        $tagString = $page ? Tag::tagStringForPage((int)$page['id']) : '';

        require WIKI_ROOT . '/templates/page/edit.php';
    }

    public function newForm(array $params): void
    {
        if (!Auth::isEditor()) { $this->forbidden(); return; }
        $group = null;
        $page  = null;
        $lang  = (string)Settings::get('site.default_lang');
        $tagString = '';

        // Pre-fill slug when creating the home page on first run
        $prefillSlug = '';
        $prefillTitle = '';
        $homeSlug = (string)Settings::get('site.home_slug', 'home');
        if (!TranslationGroup::findBySlug($homeSlug)) {
            $prefillSlug = $homeSlug;
        }

        require WIKI_ROOT . '/templates/page/edit.php';
    }

    public function save(array $params): void
    {
        if (!Auth::isEditor()) { $this->forbidden(); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $originalSlug = trim((string)($_POST['original_slug'] ?? ''));
        $newSlug      = trim((string)($_POST['slug'] ?? ''));
        $lang    = trim($_POST['lang'] ?? '') ?: (string)Settings::get('site.default_lang');
        $title   = trim($_POST['title'] ?? '');
        $content = (string)($_POST['content'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $tags    = trim((string)($_POST['tags'] ?? ''));
        $isNew   = !empty($_POST['new_group']);

        if ($title === '') { http_response_code(400); exit('Title is required'); }

        $uid = (int)Auth::user()['id'];

        if ($isNew || $originalSlug === '') {
            $baseSlug = $newSlug !== '' ? $newSlug : $title;
            $slug = TranslationGroup::uniqueSlug(TranslationGroup::makeSlug($baseSlug));
            $groupId = TranslationGroup::create($slug, $lang);
            AuditLog::log('page.create_group', 'translation_group', $groupId, ['slug' => $slug]);
        } else {
            $g = TranslationGroup::findBySlug($originalSlug);
            if (!$g) { $this->notFound('/page/' . $originalSlug); return; }
            $groupId = (int)$g['id'];
            $slug = $originalSlug;

            if ($newSlug !== '' && $newSlug !== $originalSlug) {
                $clean = TranslationGroup::makeSlug($newSlug);
                if ($clean !== $originalSlug && !TranslationGroup::findBySlug($clean)) {
                    TranslationGroup::update($groupId, ['slug' => $clean]);
                    if (Settings::getBool('feature.auto_redirect')) {
                        Redirect::autoFromSlugChange($originalSlug, $clean, $uid);
                    }
                    AuditLog::log('page.slug_change', 'translation_group', $groupId, [
                        'from' => $originalSlug, 'to' => $clean,
                    ]);
                    $slug = $clean;
                }
            }
        }

        $page = Page::findByGroupLang($groupId, $lang);
        if ($page) {
            if ((int)$page['is_locked'] === 1 && !Auth::isAdmin()) {
                $this->forbidden(I18nService::t('page.locked'));
                return;
            }
            Page::save((int)$page['id'], $title, $content, $uid, $comment);
            $pageId = (int)$page['id'];
            AuditLog::log('page.save', 'page', $pageId);
        } else {
            $pageId = Page::create($groupId, $lang, $title, $content, $uid, $comment);
            AuditLog::log('page.create', 'page', $pageId, ['lang' => $lang]);
        }

        if (Settings::getBool('feature.tags')) {
            Tag::syncForPage($pageId, $tags);
        }

        $redirect = 'page/' . $slug;
        if ($lang !== (string)Settings::get('site.default_lang')) {
            $redirect .= '/' . $lang;
        }

        // If we just created the home page, send the user to the root
        if ($slug === (string)Settings::get('site.home_slug', 'home')) {
            redirect('');
            return;
        }

        redirect($redirect);
    }

    public function history(array $params): void
    {
        $slug = $params['slug'];
        $lang = $params['lang'] ?? null;

        $group = TranslationGroup::findBySlug($slug);
        if (!$group) { $this->notFound('/page/' . $slug . '/history'); return; }

        $defaultLang = (string)Settings::get('site.default_lang', $group['default_lang']);
        if ($lang === null) $lang = $defaultLang;

        $page = Page::findByGroupLang((int)$group['id'], $lang)
             ?: Page::findByGroupLang((int)$group['id'], $group['default_lang']);
        if (!$page) { $this->notFound('/page/' . $slug . '/history'); return; }

        $versions = Page::versions((int)$page['id']);

        require WIKI_ROOT . '/templates/page/history.php';
    }

    public function diff(array $params): void
    {
        $slug = $params['slug'];
        $lang = $params['lang'] ?? null;

        $group = TranslationGroup::findBySlug($slug);
        if (!$group) { $this->notFound('/page/' . $slug . '/diff'); return; }

        $defaultLang = (string)Settings::get('site.default_lang', $group['default_lang']);
        if ($lang === null) $lang = $defaultLang;

        $page = Page::findByGroupLang((int)$group['id'], $lang)
             ?: Page::findByGroupLang((int)$group['id'], $group['default_lang']);
        if (!$page) { $this->notFound('/page/' . $slug . '/diff'); return; }

        $v1 = (int)($_GET['v1'] ?? 0);
        $v2 = (int)($_GET['v2'] ?? 0);
        $mode = ($_GET['mode'] ?? 'side') === 'unified' ? 'unified' : 'side';

        $versions = Page::versions((int)$page['id']);
        if (!$versions) { $this->notFound('/page/' . $slug . '/diff'); return; }

        if ($v1 === 0 || $v2 === 0) {
            $v2 = $v2 ?: (int)$versions[0]['version'];
            $v1 = $v1 ?: (int)($versions[1]['version'] ?? $v2);
        }

        $left = $right = null;
        foreach ($versions as $v) {
            if ((int)$v['version'] === $v1) $left  = $v;
            if ((int)$v['version'] === $v2) $right = $v;
        }
        if (!$left || !$right) { $this->notFound('/page/' . $slug . '/diff'); return; }

        $diff  = DiffService::diff((string)$left['content'], (string)$right['content']);
        $stats = DiffService::stats($diff);

        $diffHtml = $mode === 'unified'
            ? DiffService::renderUnified($diff)
            : DiffService::renderSideBySide($diff);

        require WIKI_ROOT . '/templates/page/diff.php';
    }

    public function rollback(array $params): void
    {
        if (!Auth::isEditor()) { $this->forbidden(); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $pageId  = (int)($_POST['page_id'] ?? 0);
        $version = (int)($_POST['version'] ?? 0);
        if (!$pageId || !$version) { http_response_code(400); exit('Missing parameters'); }

        $page = Page::find($pageId);
        if (!$page) { $this->notFound('/'); return; }

        if ((int)$page['is_locked'] === 1 && !Auth::isAdmin()) {
            $this->forbidden(I18nService::t('page.locked'));
            return;
        }

        $target = null;
        foreach (Page::versions($pageId) as $v) {
            if ((int)$v['version'] === $version) { $target = $v; break; }
        }
        if (!$target) { http_response_code(404); exit('Version not found'); }

        $uid = (int)Auth::user()['id'];

        Page::save(
            $pageId,
            (string)$target['title'],
            (string)$target['content'],
            $uid,
            I18nService::t('page.rollback_comment', ['v' => $version])
        );
        AuditLog::log('page.rollback', 'page', $pageId, ['version' => $version]);

        $g = TranslationGroup::find((int)$page['group_id']);
        $redirect = 'page/' . ($g['slug'] ?? '');
        if ($page['lang'] !== (string)Settings::get('site.default_lang')) {
            $redirect .= '/' . $page['lang'];
        }
        redirect($redirect);
    }

    public function switchLang(array $params): void
    {
        I18nService::set($params['code'] ?? '');
        $back = $_SERVER['HTTP_REFERER'] ?? url('');
        header('Location: ' . $back);
        exit;
    }

    private function notFound(string $path): void
    {
        http_response_code(404);
        NotFoundLog::record($path);

        $custom   = (string)Settings::get('seo.404_content', '');
        $siteName = e((string)Settings::get('site.name'));
        $lang     = e(I18nService::current());
        $t        = fn(string $k, array $p = []) => I18nService::t($k, $p);

        $suggestions = NotFoundLog::suggest($path, 3);

        echo '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8">'
           . '<meta name="viewport" content="width=device-width,initial-scale=1">'
           . '<title>404 - ' . $siteName . '</title>'
           . '<script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-50">'
           . '<div class="max-w-lg mx-auto mt-24 text-center font-sans">'
           . '<h1 class="text-5xl font-bold text-slate-800">404</h1>'
           . '<p class="mt-4 text-slate-500">' . e($t('error.page_not_found')) . '</p>';

        if ($suggestions) {
            echo '<div class="mt-6 text-left bg-white rounded shadow p-4">'
               . '<div class="text-xs uppercase text-slate-400 mb-2">'
               . e($t('search.title')) . '</div><ul class="space-y-1">';
            foreach ($suggestions as $s) {
                echo '<li><a class="text-blue-600 hover:underline" href="'
                   . e(url('page/' . $s['slug'])) . '">' . e($s['slug']) . '</a></li>';
            }
            echo '</ul></div>';
        }

        if ($custom !== '') {
            echo '<div class="mt-6 text-left">' . $custom . '</div>';
        }

        echo '<p class="mt-6"><a href="' . e(url('')) . '" class="text-blue-600 hover:underline">'
           . e($t('nav.home')) . '</a></p></div></body></html>';
    }

    private function forbidden(string $msg = ''): void
    {
        http_response_code(403);
        $msg  = $msg ?: I18nService::t('error.forbidden');
        $lang = e(I18nService::current());

        echo '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8">'
           . '<title>403</title><script src="https://cdn.tailwindcss.com"></script></head><body>'
           . '<div class="max-w-lg mx-auto mt-24 text-center font-sans">'
           . '<h1 class="text-5xl font-bold text-slate-800">403</h1>'
           . '<p class="mt-4 text-slate-500">' . e($msg) . '</p>'
           . '<p class="mt-6"><a href="' . e(url('')) . '" class="text-blue-600 hover:underline">'
           . e(I18nService::t('nav.home')) . '</a></p></div></body></html>';
    }
}
