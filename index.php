<?php
declare(strict_types=1);

require __DIR__ . '/src/Bootstrap.php';

use Wiki\Router;
use Wiki\Controllers\ApiController;
use Wiki\Controllers\AttachmentController;
use Wiki\Controllers\IssueController;
use Wiki\Controllers\PageController;
use Wiki\Controllers\ProposalController;
use Wiki\Controllers\SearchController;
use Wiki\Controllers\UserController;
use Wiki\Models\Redirect;
use Wiki\Models\Settings;

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if ($base && $base !== '/' && str_starts_with($requestPath, $base)) {
    $requestPath = substr($requestPath, strlen($base));
}
$requestPath = '/' . ltrim($requestPath, '/');

try {
    $redirectRow = Redirect::findByPath($requestPath);
    if ($redirectRow) {
        Redirect::hit((int)$redirectRow['id']);
        header('Location: ' . $redirectRow['to_path'], true, (int)$redirectRow['status_code']);
        exit;
    }
} catch (\Throwable $e) {
    // redirects table may not exist during install
}

$router = new Router();
$pc = new PageController();
$ic = new IssueController();
$rc = new ProposalController();
$sc = new SearchController();
$ac = new AttachmentController();
$uc = new UserController();
$api = new ApiController();

$router->get('/lang/{code}', fn($p) => $pc->switchLang($p));

// Authentication
$router->get('/login',                fn($p) => $uc->loginForm());
$router->post('/login',               fn($p) => $uc->login());
$router->get('/register',             fn($p) => $uc->registerForm());
$router->post('/register',            fn($p) => $uc->register());
$router->get('/logout',               fn($p) => $uc->logout());
$router->get('/verify',               fn($p) => $uc->verifyNotice());
$router->get('/verify/{token}',       fn($p) => $uc->verify($p['token']));
$router->post('/verify/resend',       fn($p) => $uc->resendVerify());
$router->get('/forgot',               fn($p) => $uc->forgotForm());
$router->post('/forgot',              fn($p) => $uc->forgot());
$router->get('/reset/{token}',        fn($p) => $uc->resetForm($p['token']));
$router->post('/reset',               fn($p) => $uc->reset());
$router->get('/auth/github',          fn($p) => $uc->githubStart());
$router->get('/auth/github/callback', fn($p) => $uc->githubCallback());

// Profile
$router->get('/profile',          fn($p) => $uc->profileForm());
$router->post('/profile',         fn($p) => $uc->profileUpdate());
$router->post('/profile/password',fn($p) => $uc->profilePassword());

$router->get('/', function () use ($pc) {
    $pc->home();
});

// Read-only JSON API
$router->get('/api/pages',              fn($p) => $api->pages());
$router->get('/api/page/{slug}/{lang}', fn($p) => $api->page($p));
$router->get('/api/page/{slug}',        fn($p) => $api->page($p));

// Attachments
$router->post('/api/upload',    fn($p) => $ac->upload());
$router->post('/api/test-s3',   fn($p) => $ac->testS3());

$router->get('/search',       fn($p) => $sc->search());
$router->get('/tags',         fn($p) => $sc->tags());
$router->get('/tag/{slug}',   fn($p) => $sc->tag($p));
$router->get('/sitemap.xml',  fn($p) => $sc->sitemap());
$router->get('/rss.xml',      fn($p) => $sc->rss());

$router->get('/issues',                fn($p) => $ic->index());
$router->get('/issues/new',            fn($p) => $ic->newForm($p));
$router->post('/issues',               fn($p) => $ic->create());
$router->get('/issue/{id}',            fn($p) => $ic->view($p));
$router->post('/issue/{id}/comment',   fn($p) => $ic->comment($p));
$router->post('/issue/{id}/close',     fn($p) => $ic->close($p));
$router->post('/issue/{id}/reopen',    fn($p) => $ic->reopen($p));
$router->post('/issue/{id}/delete',    fn($p) => $ic->delete($p));

$router->get('/proposals',              fn($p) => $rc->index());
$router->post('/proposals',             fn($p) => $rc->create());
$router->get('/proposal/{id}',          fn($p) => $rc->view($p));
$router->post('/proposal/{id}/comment', fn($p) => $rc->comment($p));
$router->post('/proposal/{id}/merge',   fn($p) => $rc->merge($p));
$router->post('/proposal/{id}/close',   fn($p) => $rc->close($p));
$router->post('/proposal/{id}/reopen',  fn($p) => $rc->reopen($p));
$router->post('/proposal/{id}/delete',  fn($p) => $rc->delete($p));

$router->get('/new', fn($p) => $pc->newForm($p));

$router->get('/page/{slug}/{lang}/history', fn($p) => $pc->history($p));
$router->get('/page/{slug}/history',        fn($p) => $pc->history($p));

$router->get('/page/{slug}/{lang}/diff', fn($p) => $pc->diff($p));
$router->get('/page/{slug}/diff',        fn($p) => $pc->diff($p));

$router->get('/page/{slug}/{lang}/propose', fn($p) => $rc->newForm($p));
$router->get('/page/{slug}/propose',        fn($p) => $rc->newForm($p));

$router->get('/page/{slug}/{lang}/edit', fn($p) => $pc->edit($p));
$router->get('/page/{slug}/edit',        fn($p) => $pc->edit($p));

$router->post('/page/save',     fn($p) => $pc->save($p));
$router->post('/page/rollback', fn($p) => $pc->rollback($p));

$router->get('/page/{slug}/{lang}', fn($p) => $pc->view($p));
$router->get('/page/{slug}',        fn($p) => $pc->view($p));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
