<?php
declare(strict_types=1);

namespace Wiki\Controllers;

use Wiki\Auth\Auth;
use Wiki\Models\AuditLog;
use Wiki\Models\Comment;
use Wiki\Models\Issue;
use Wiki\Models\Settings;
use Wiki\Models\TranslationGroup;
use Wiki\Services\I18nService;
use Wiki\Services\NotificationService;

class IssueController
{
    public function index(): void
    {
        if (!Settings::getBool('feature.issues')) { $this->forbidden(); return; }

        $status = $_GET['status'] ?? 'open';
        if (!in_array($status, ['open', 'closed', 'all'], true)) $status = 'open';

        $issues = Issue::listAll($status === 'all' ? '' : $status, 100, 0);
        $pageTitle = I18nService::t('issue.title');
        require WIKI_ROOT . '/templates/issue/list.php';
    }

    public function newForm(array $params): void
    {
        if (!Settings::getBool('feature.issues')) { $this->forbidden(); return; }
        if (!Auth::check()) { redirect('login'); return; }

        $groupId = isset($_GET['group']) ? (int)$_GET['group'] : 0;
        $group = $groupId ? TranslationGroup::find($groupId) : null;
        $pageTitle = I18nService::t('issue.new_title');
        require WIKI_ROOT . '/templates/issue/new.php';
    }

    public function create(): void
    {
        if (!Settings::getBool('feature.issues')) { $this->forbidden(); return; }
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $groupId = (int)($_POST['group_id'] ?? 0);
        $title   = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($groupId <= 0 || $title === '') { http_response_code(400); exit('Missing fields'); }
        if (!TranslationGroup::find($groupId)) { http_response_code(404); exit('Group not found'); }

        $id = Issue::create([
            'group_id'   => $groupId,
            'page_id'    => !empty($_POST['page_id']) ? (int)$_POST['page_id'] : null,
            'title'      => $title,
            'content'    => $content,
            'created_by' => (int)Auth::user()['id'],
        ]);
        AuditLog::log('issue.create', 'issue', $id);
        redirect('issue/' . $id);
    }

    public function view(array $params): void
    {
        if (!Settings::getBool('feature.issues')) { $this->forbidden(); return; }

        $id = (int)$params['id'];
        $issue = Issue::find($id);
        if (!$issue) { http_response_code(404); exit('Issue not found'); }

        $comments  = Comment::listFor('issue', $id);
        $canManage = Auth::isEditor() || (Auth::check() && (int)Auth::user()['id'] === (int)$issue['created_by']);

        $pageTitle = $issue['title'];
        require WIKI_ROOT . '/templates/issue/view.php';
    }

    public function comment(array $params): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        $issue = Issue::find($id);
        if (!$issue) { http_response_code(404); exit('Issue not found'); }

        $content = trim($_POST['content'] ?? '');
        if ($content === '') { redirect('issue/' . $id); return; }

        $uid = (int)Auth::user()['id'];
        Comment::create('issue', $id, $content, $uid);
        Issue::touch($id);
        AuditLog::log('issue.comment', 'issue', $id);

        try {
            NotificationService::notifyIssueComment($id, $uid, $content);
        } catch (\Throwable $e) {
            // never let mail failure break the request
        }

        redirect('issue/' . $id);
    }

    public function close(array $params): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        $issue = Issue::find($id);
        if (!$issue) { http_response_code(404); exit('Issue not found'); }

        $uid = (int)Auth::user()['id'];
        if (!Auth::isEditor() && $uid !== (int)$issue['created_by']) { $this->forbidden(); return; }

        Issue::setStatus($id, 'closed');
        AuditLog::log('issue.close', 'issue', $id);
        redirect('issue/' . $id);
    }

    public function reopen(array $params): void
    {
        if (!Auth::check()) { redirect('login'); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        $issue = Issue::find($id);
        if (!$issue) { http_response_code(404); exit('Issue not found'); }

        $uid = (int)Auth::user()['id'];
        if (!Auth::isEditor() && $uid !== (int)$issue['created_by']) { $this->forbidden(); return; }

        Issue::setStatus($id, 'open');
        AuditLog::log('issue.reopen', 'issue', $id);
        redirect('issue/' . $id);
    }

    public function delete(array $params): void
    {
        if (!Auth::isAdmin()) { $this->forbidden(); return; }
        if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) { http_response_code(400); exit('CSRF check failed'); }

        $id = (int)$params['id'];
        if (!Issue::find($id)) { http_response_code(404); exit('Issue not found'); }

        Issue::delete($id);
        AuditLog::log('issue.delete', 'issue', $id);
        redirect('issues');
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
