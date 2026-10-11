<?php
declare(strict_types=1);

namespace Wiki\Controllers;

use Wiki\Auth\Auth;
use Wiki\Models\AuditLog;
use Wiki\Models\Comment;
use Wiki\Models\Page;
use Wiki\Models\Proposal;
use Wiki\Models\Settings;
use Wiki\Models\TranslationGroup;
use Wiki\Services\DiffService;
use Wiki\Services\I18nService;
use Wiki\Services\NotificationService;

class ProposalController
{
    public function index(): void
    {
        if (!Settings::getBool('feature.proposals')) { $this->forbidden(); return; }

        $status = $_GET['status'] ?? 'open';
        if (!in_array($status, ['open', 'merged', 'closed', 'all'], true)) $status = 'open';

        $proposals = Proposal::listAll($status === 'all' ? '' : $status, 100, 0);
        $pageTitle = I18nService::t('proposal.title');
        require WIKI_ROOT . '/templates/proposal/list.php';
    }

    public function newForm(array $params): void
    {
        if (!Settings::getBool('feature.proposals')) { $this->forbidden(); return; }
        if (!Auth::check()) { redirect('login'); return; }

        $slug = $params['slug'];
        $lang = $params['lang'] ?? (string)Settings::get('site.default_lang');

        $group = TranslationGroup::findBySlug($slug);
        if (!$group) { http_response_code(404); exit('Page not found'); }

        $page = Page::findByGroupLang((int)$group['id'], $lang)
             ?: Page::findByGroupLang((int)$group['id'], $group['default_lang']);
        if (!$page) { http_response_code(404); exit('Page not found'); }

        $pageTitle = I18nService::t('proposal.new_title');
        require WIKI_ROOT . '/templates/proposal/new.php';
    }

    public function create(): void
    {
        if (!Settings::getBool('feature.proposals')) { $this->forbidden(); return; }
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $pageId    = (int)($_POST['page_id'] ?? 0);
        $title     = trim($_POST['title'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $propTitle = trim($_POST['proposed_title'] ?? '');
        $propBody  = (string)($_POST['proposed_content'] ?? '');

        $page = Page::find($pageId);
        if (!$page) { http_response_code(404); exit('Page not found'); }
        if ($title === '') { http_response_code(400); exit('Title is required'); }
        if ($propTitle === '') $propTitle = (string)$page['title'];

        $id = Proposal::create([
            'page_id'          => $pageId,
            'title'            => $title,
            'description'      => $desc,
            'base_version'     => (int)$page['current_version'],
            'proposed_title'   => $propTitle,
            'proposed_content' => $propBody,
            'created_by'       => (int)Auth::user()['id'],
        ]);
        AuditLog::log('proposal.create', 'proposal', $id);
        redirect('proposal/' . $id);
    }

    public function view(array $params): void
    {
        if (!Settings::getBool('feature.proposals')) { $this->forbidden(); return; }

        $id = (int)$params['id'];
        $proposal = Proposal::find($id);
        if (!$proposal) { http_response_code(404); exit('Proposal not found'); }

        $page = Page::find((int)$proposal['page_id']);
        if (!$page) { http_response_code(404); exit('Page not found'); }

        $group = TranslationGroup::find((int)$page['group_id']);
        $base = Proposal::baseVersion($proposal);
        $baseContent = $base ? (string)$base['content'] : '';

        $diff  = DiffService::diff($baseContent, (string)$proposal['proposed_content']);
        $stats = DiffService::stats($diff);

        $hasConflict = (int)$proposal['base_version'] < (int)$page['current_version']
                    && $proposal['status'] === 'open';

        $comments   = Comment::listFor('proposal', $id);
        $canReview  = Auth::isEditor();
        $canManage  = Auth::isEditor() || (Auth::check() && (int)Auth::user()['id'] === (int)$proposal['created_by']);

        $pageTitle = $proposal['title'];
        require WIKI_ROOT . '/templates/proposal/view.php';
    }

    public function comment(array $params): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        if (!Proposal::find($id)) { http_response_code(404); exit('Proposal not found'); }

        $content = trim($_POST['content'] ?? '');
        if ($content === '') { redirect('proposal/' . $id); return; }

        $uid = (int)Auth::user()['id'];
        Comment::create('proposal', $id, $content, $uid);
        Proposal::touch($id);
        AuditLog::log('proposal.comment', 'proposal', $id);

        try {
            NotificationService::notifyProposalComment($id, $uid, $content);
        } catch (\Throwable $e) {}

        redirect('proposal/' . $id);
    }

    public function merge(array $params): void
    {
        if (!Auth::isEditor()) { $this->forbidden(); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        $proposal = Proposal::find($id);
        if (!$proposal) { http_response_code(404); exit('Proposal not found'); }
        if ($proposal['status'] !== 'open') { redirect('proposal/' . $id); return; }

        $page = Page::find((int)$proposal['page_id']);
        if (!$page) { http_response_code(404); exit('Page not found'); }

        if ((int)$page['is_locked'] === 1 && !Auth::isAdmin()) { http_response_code(403); exit('Page is locked'); }

        $allowStaleMerge = !empty($_POST['force']);
        if ((int)$proposal['base_version'] < (int)$page['current_version'] && !$allowStaleMerge) {
            redirect('proposal/' . $id . '?conflict=1');
            return;
        }

        $uid = (int)Auth::user()['id'];
        $newVersion = Page::save(
            (int)$page['id'],
            (string)$proposal['proposed_title'],
            (string)$proposal['proposed_content'],
            $uid,
            I18nService::t('proposal.merge_comment', ['id' => $id, 'title' => $proposal['title']])
        );

        Proposal::setStatus($id, 'merged', $uid, $newVersion);
        AuditLog::log('proposal.merge', 'proposal', $id, ['version' => $newVersion]);

        redirect('proposal/' . $id);
    }

    public function close(array $params): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        $proposal = Proposal::find($id);
        if (!$proposal) { http_response_code(404); exit('Proposal not found'); }

        $uid = (int)Auth::user()['id'];
        if (!Auth::isEditor() && $uid !== (int)$proposal['created_by']) { $this->forbidden(); return; }

        Proposal::setStatus($id, 'closed', $uid);
        AuditLog::log('proposal.close', 'proposal', $id);
        redirect('proposal/' . $id);
    }

    public function reopen(array $params): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        $proposal = Proposal::find($id);
        if (!$proposal) { http_response_code(404); exit('Proposal not found'); }

        if (!Auth::isEditor() && (int)Auth::user()['id'] !== (int)$proposal['created_by']) {
            $this->forbidden();
            return;
        }

        Proposal::setStatus($id, 'open', (int)Auth::user()['id']);
        AuditLog::log('proposal.reopen', 'proposal', $id);
        redirect('proposal/' . $id);
    }

    public function delete(array $params): void
    {
        if (!Auth::isAdmin()) { $this->forbidden(); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        if (!Proposal::find($id)) { http_response_code(404); exit('Proposal not found'); }

        Proposal::delete($id);
        AuditLog::log('proposal.delete', 'proposal', $id);
        redirect('proposals');
    }

    private function forbidden(): void
    {
        http_response_code(403);
        $lang = e(I18nService::current());
        $msg  = e(I18nService::t('error.forbidden'));
        echo '<!doctype html><html lang="' . $lang . '"><head><meta charset="utf-8">'
           . '<title>403</title><script src="https://cdn.tailwindcss.com"></script></head><body>'
           . '<div class="max-w-lg mx-auto mt-24 text-center font-sans">'
           . '<h1 class="text-5xl font-bold text-slate-800">403</h1>'
           . '<p class="mt-4 text-slate-500">' . $msg . '</p></div></body></html>';
    }
}
